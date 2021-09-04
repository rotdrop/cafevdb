<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Service;

use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;

/**@todo Base on \ArrayObject */
class RequestParameterService implements \ArrayAccess, \Countable
{
  const MAGIC_KEYS = [
    'get',
    'post',
    'files',
    'server',
    'env',
    'cookies',
    'urlParams',
    'parameters',
    'method',
    'requesttoken',
  ];

  /** @var IRequest */
  private $request;

  /** @var array */
  private $parameters;

  public function __construct(IRequest $request) {
    $this->request = $request;
    $this->parameters = array_merge([ 'renderAs' => 'user',
                                      'projectName' => '',
                                      'projectId' => null,
                                      'musicianId' => null ],
                                    $this->request->getParams());
  }

  public function getRequest() {
    return $this->request;
  }

  public function reset() {
    $this->parameters = $this->request->getParams();
  }

  public function getRemoteAddress()
  {
    return $this->request->getRemoteAddress();
  }

  public function getRequestUri()
  {
    return $this->request->getRequestUri();
  }

  public function getParam($key, $default = null) {
    if (array_key_exists($key, $this->parameters)) {
      return $this->parameters[$key];
    } else {
      return $default;
    }
  }

  public function setParam($key, $value) {
    $this->parameters[$key] = $value;
  }

  public function getParams(): array
  {
    return $this->parameters;
  }

  public function setParams($parameters): array {
    $old = $this->parameters;

    $this->parameters = Util::arrayMergeRecursive(
      [ 'renderAs' => 'user',
        'projectName' => '',
        'projectId' => null,
        'musicianId' => null ],
      $this->request->getParams(),
      $parameters);

    return $old;
  }

  /**
   * Get all request parameters matching the given prefix at the start
   * as an associated array, with the prefix removed.
   *
   * @param string $prefix
   *
   * @return array<string, mixed>
   */
  public function getPrefixParams(string $prefix):array
  {
    $result = [];
    foreach ($this->parameters as $key => $value)
      if (strpos($key, $prefix) === 0) {
        $outKey = substr($key, strlen($prefix));
        $result[$outKey] = $value;
      }
    return $result;
  }

  /**
   * Countable method
   * @return int
   */
  public function count(): int {
    return \count($this->parameters);
  }

  /**
   * ArrayAccess methods
   *
   * @param string $offset The key to lookup
   * @return boolean
   */
  public function offsetExists($offset): bool {
    return isset($this->parameters[$offset]);
  }

  /**
   * @see offsetExists
   * @param string $offset
   * @return mixed
   */
  public function offsetGet($offset) {
    return isset($this->parameters[$offset])
      ? $this->parameters[$offset]
      : null;
  }

  /**
   * @see offsetExists
   * @param string $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    $this->parameters[$offset] = $value;
  }

  /**
   * @see offsetExists
   * @param string $offset
   */
  public function offsetUnset($offset) {
    unset($this->parameters[$offset]);
  }

  /**
   * Magic property accessors
   * @param string $name
   * @param mixed $value
   */
  public function __set($name, $value) {
    $this->parameters[$name] = $value;
  }

  public function __get($name) {
    if (isset($this[$name])) {
      return $this[$name];
    }
    return $this->request->__get($name);
  }

  /**
   * @param string $name
   * @return bool
   */
  public function __isset($name) {
    return isset($this[$name]) || $this->request->__isset($name);
  }

  /**
   * @param string $id
   */
  public function __unset($id) {
    if (isset($this->parameters[$name])) {
      unset($this->parameters[$name]);
    }
  }

  public function getUpload($name)
  {
    return $this->request->getUploadedFile($name);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
