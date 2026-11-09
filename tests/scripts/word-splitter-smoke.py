#!/usr/bin/env python3
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Exercise only the splitter files extracted from an app-store tarball."""

import http.client
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import tarfile
import tempfile
import time


def request(socket_path, route, payload=None):
    connection = http.client.HTTPConnection("localhost", timeout=60)
    connection.sock = socket.socket(socket.AF_UNIX)
    connection.sock.settimeout(60)
    try:
        connection.sock.connect(str(socket_path))
        connection.request(
            "GET" if payload is None else "POST",
            route,
            body=None if payload is None else json.dumps(payload),
            headers={"Content-Type": "application/json"},
        )
        response = connection.getresponse()
        body = response.read().decode()
        assert response.status == 200, (response.status, body)
        return json.loads(body)
    finally:
        connection.close()


def main(archive):
    with tempfile.TemporaryDirectory(prefix="splitter-smoke-") as temporary:
        root = Path(temporary)
        installed = root / "relocated app" / "python"
        installed.mkdir(parents=True)
        filenames = {"german-word-splitter.pyz", "german_utf8_linux.dic"}
        with tarfile.open(archive) as package:
            files = [
                member for member in package.getmembers()
                if member.name.startswith("cafevdb/python/") and not member.isdir()
            ]
            assert {member.name for member in files} == {
                f"cafevdb/python/{filename}" for filename in filenames
            }, "Archive must contain exactly the zipapp and dictionary under python/"
            assert len(files) == len(filenames), "Duplicate splitter archive members"
            for member in files:
                assert member.isfile(), f"Not a regular file: {member.name}"
                target = installed / Path(member.name).name
                with package.extractfile(member) as source:
                    target.write_bytes(source.read())
                target.chmod(member.mode)
        executable = installed / "german-word-splitter.pyz"
        assert os.access(executable, os.X_OK), "Zipapp is not executable"
        assert (installed / "german_utf8_linux.dic").stat().st_size > 0
        environment = {
            key: value for key, value in os.environ.items()
            if not key.startswith(("GERMAN_WORD_SPLITTER_", "SHIV_"))
            and key != "PYTHONPATH"
        }
        socket_path = root / "splitter.sock"
        environment.update(
            GERMAN_WORD_SPLITTER_SOCKET=str(socket_path),
            GERMAN_WORD_SPLITTER_DICTIONARY=str(installed / "german_utf8_linux.dic"),
            GERMAN_WORD_SPLITTER_TRANSLITERATION_LOCALES="C.UTF-8",
            SHIV_ROOT=str(root / "cache"),
        )
        with tempfile.TemporaryFile(mode="w+") as log:
            process = subprocess.Popen(
                [str(executable)], cwd=root, env=environment,
                stdout=log, stderr=subprocess.STDOUT,
            )
            try:
                deadline = time.monotonic() + 60
                while True:
                    assert process.poll() is None, "Splitter exited during startup"
                    try:
                        info = request(socket_path, "/info")
                        break
                    except (FileNotFoundError, ConnectionRefusedError):
                        assert time.monotonic() < deadline, "Splitter startup timed out"
                        time.sleep(0.1)
                assert info["version"]
                assert info["transliteration_locales"] == ["C.UTF-8"], info
                for method in ("charsplit", "gcs"):
                    response = request(socket_path, "/split", {
                        "word": "Haustür", "method": method,
                    })
                    assert response["splittable"], (method, response)
                    assert [part.lower() for part in response["components"]] == [
                        "haus", "tür",
                    ], (method, response)
                assert any((root / "cache").iterdir()), "Zipapp did not use SHIV_ROOT"
            finally:
                process.terminate()
                try:
                    process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait()
                log.seek(0)
                print(log.read(), end="")
    print("Relocated splitter archive: both backends passed.")


if __name__ == "__main__":
    main(sys.argv[1])
