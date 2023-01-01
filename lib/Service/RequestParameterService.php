<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(IRequest $request)
  {
    $this->request = $request;
    $this->parameters = array_merge(
      [
        'renderAs' => 'user',
        'projectName' => '',
        'projectId' => null,
        'musicianId' => null,
      ],
      $this->request->getParams());
  }
  // phpcs:enable

  /** @return IRequest */
  public function getRequest():IRequest
  {
    return $this->request;
  }

  /** @return void */
  public function reset():void
  {
    $this->parameters = $this->request->getParams();
  }

  /** @return string */
  public function getRemoteAddress():string
  {
    return $this->request->getRemoteAddress();
  }

  /** @return string */
  public function getRequestUri()
  {
    return $this->request->getRequestUri();
  }

  /**
   * @param string $key
   *
   * @param mixed $default
   *
   * @return mixed
   */
  public function getParam(string $key, mixed $default = null)
  {
    if (array_key_exists($key, $this->parameters)) {
      return $this->parameters[$key];
    } else {
      return $default;
    }
  }

  /**
   * @param string $key
   *
   * @param mixed $value
   *
   * @return void
   */
  public function setParam(string $key, mixed $value):void
  {
    $this->parameters[$key] = $value;
  }

  /** @return array */
  public function getParams():array
  {
    return $this->parameters;
  }

  /**
   * @param array $parameters
   *
   * @return array
   */
  public function setParams(array $parameters):array
  {
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
    foreach ($this->parameters as $key => $value) {
      if (strpos($key, $prefix) === 0) {
        $outKey = substr($key, strlen($prefix));
        $result[$outKey] = $value;
      }
    }

    return $result;
  }

  /** {@inheritdoc} */
  public function count():int
  {
    return \count($this->parameters);
  }

  /** {@inheritdoc} */
  public function offsetExists($offset):bool
  {
    return isset($this->parameters[$offset]);
  }

  /** {@inheritdoc} */
  public function offsetGet($offset)
  {
    return isset($this->parameters[$offset])
      ? $this->parameters[$offset]
      : null;
  }

  /** {@inheritdoc} */
  public function offsetSet($offset, $value)
  {
    $this->parameters[$offset] = $value;
  }

  /** {@inheritdoc} */
  public function offsetUnset($offset)
  {
    unset($this->parameters[$offset]);
  }

  /** {@inheritdoc} */
  public function __set($name, $value)
  {
    $this->parameters[$name] = $value;
  }

  /** {@inheritdoc} */
  public function __get($name)
  {
    if (isset($this[$name])) {
      return $this[$name];
    }
    return $this->request->__get($name);
  }

  /** {@inheritdoc} */
  public function __isset($name)
  {
    return isset($this[$name]) || $this->request->__isset($name);
  }

  /** {@inheritdoc} */
  public function __unset($id)
  {
    if (isset($this->parameters[$id])) {
      unset($this->parameters[$id]);
    }
  }

  /**
   * @param string $name
   *
   * @return array
   */
  public function getUpload(string $name):array
  {
    return $this->request->getUploadedFile($name);
  }
}
