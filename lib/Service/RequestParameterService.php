<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020
 */

namespace OCA\CAFEVDB\Service;

use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;

/**@TODO Base on \ArrayObject */
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
                                      'projectId' => -1,
                                      'musicianId' => -1 ],
                                    $this->request->getParams());
  }

  public function getRequest() {
    return $this->request;
  }

  public function reset() {
    $this->parameters = $this->request->getParams();
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

    $this->parameters = Util::arrayMergerRecursive(
      [ 'renderAs' => 'user',
        'projectName' => '',
        'projectId' => -1,
        'musicianId' => -1 ],
      $this->request->getParams(),
      $parameters);

    return $old;
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
