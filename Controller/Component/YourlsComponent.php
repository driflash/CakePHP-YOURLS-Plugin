<?php
/**
 * Yourls.Yourls
 * Uses remote calls to short the url.
 *
 * @author Adriano Luís Rocha <driflash [at] gmail [dot] com>
 * @since 0.7
 * @license MIT
 */
App::uses('Xml', 'Utility');
App::uses('HttpSocket', 'Network/Http');

Configure::load('yourls');

class YourlsComponent extends Component
{

	/**
	 * Admin username
	 *
	 * @var string
	 */
	private $__username;

	/**
	 * Admin password
	 *
	 * @var string
	 */
	private $__password;

	/**
	 * A secret signature token is unique, associated to one account,
	 * and can be used only for API requests. You will find it in the
	 * Tools page of your YOURLS install.
	 *
	 * @var string Your secret signature token
	 */
	private $__signature;

	/**
	 * YOURLS installation URL, no trailing slash
	 *
	 * @var string
	 */
	private $__url;

	/**
	 * Handle HttpSocket instance from CakePHP Core
	 *
	 * @var HttpSocket
	 */
	private $__httpSocket;

	/**
	 * Available file formats to comunicate with Yourls API.
	 *
	 * @var string
	 */
	private $__formats = array('json', 'xml', 'simple');

	/**
	 * Available filters for statistics.
	 *
	 * @var string
	 */
	private $__filters = array('top', 'bottom', 'rand', 'last');

	/**
	 * Available communication methods
	 *
	 * @var string
	 */
	public $availableRequestMethod = array(
		'get',
		'post'
	);

	/**
	 * Response format
	 *
	 * @var string
	 */
	public $format = 'simple';

	/**
	 * Response filter for stats
	 *
	 * @var string
	 */
	public $filter = 'top';

	/**
	 * Request method
	 *
	 * @var string
	 */
	public $requestMethod = 'get';

	/**
	 * Convert response into array
	 *
	 * @param string $response from remote call to YOURLS api
	 */
	private function process(HttpSocketResponse $response)
	{
		$array = array();
		if (!empty($response)) {
			$body = $response->body();
			if ($this->format === 'xml') {
				$xml = Xml::build($body);
				$temp = Xml::toArray($xml);
				$array = array(
					'url' => $temp['result']['shorturl']
				);
				$temp = null;
				$xml = null;
				unset($temp);
				unset($xml);
			} elseif ($this->format === 'json') {
				$temp = json_decode($body, true);
				$array = array(
					'url' => $temp['shorturl']
				);
				$temp = null;
				unset($temp);
			} elseif ($this->format === 'simple') {
				$array = array(
					'url' => $body
				);
			}
		}
		return $array;
	}

	/**
	 * Calls HttpSocket request method using auth options
	 *
	 * @param array $query array with request parameters
	 */
	private function request($query)
	{
		$url = "{$this->__url}/yourls-api.php";

		if (!empty($this->__signature)) {
			$query = array_merge($query, array('signature' => $this->__signature));
		} elseif (!empty($this->__username) && !empty($this->__password)) {
			$query = array_merge($query, array('username' => $this->__username, 'password' => $this->__password));
		}
		if ($this->requestMethod === 'get') {
			return $this->__httpSocket->get($url, $query);
		} elseif ($this->requestMethod === 'post') {
			return $this->__httpSocket->post($url, $query);
		} else {
			return false;
		}
	}

	/**
	 *
	 * @see Component::beforeRender($controller)
	 */
	public function beforeRender(Controller $controller)
	{
		if (isset($controller->shortIt) && $controller->shortIt === true) {
			if (isset($controller->pageTitle)) {
				$controller->set('shorturl', $this->shorturl("http://{$_SERVER['SERVER_NAME']}{$controller->request->here}", $controller->pageTitle));
			} else {
				trigger_error(__('No page title provided. Impossible to short URL.'), E_USER_ERROR);
			}
		}
	}

	/**
	 *
	 * @see Component::startup($controller)
	 */
	public function startup(Controller $controller)
	{
		$this->__httpSocket =& new HttpSocket();
		$this->__url = Configure::read('Yourls.url');
		if (Configure::read('Yourls.signature')) {
			$this->__signature = Configure::read('Yourls.signature');
		} elseif (Configure::read('Yourls.username') && Configure::read('Yourls.password')) {
			$this->__username = Configure::read('Yourls.username');
			$this->__password = Configure::read('Yourls.password');
		} else {
			trigger_error(__('No authentication provided!'), E_USER_ERROR);
		}
	}

	/**
	 *
	 * @see Component::__construct($collection, $settings)
	 */
	public function __construct(ComponentCollection $collection, $settings = array())
	{
		if (isset($settings['format']) && !in_array($settings['format'], $this->__formats)) {
			trigger_error(__('Invalid value for \'format\' setting.'), E_USER_WARNING);
			unset($settings['format']);
		}
		if (isset($settings['filter']) && !in_array($settings['filter'], $this->__filters)) {
			trigger_error(__('Invalid value for \'filter\' setting.'), E_USER_WARNING);
			unset($settings['filter']);
		}
		parent::__construct($collection, $settings);
	}

	/**
	 * Get short URL for a link
	 *
	 * @param string $url to shorten
	 * @param string $title title for url
	 * @param string $keyword [optional] for custom short URLs
	 * @param string $format [optional] either "json" or "xml"
	 */
	public function shorturl($url, $title, $keyword = null, $format = null)
	{
		if (empty($format)) {
			$format = $this->format;
		}
		$query = array(
			'action' => 'shorturl',
			'url' => $url,
			'title' => $title,
			'format' => $format
		);
		if (!empty($keyword)) {
			$query = array_merge($query, array('keyword' => $keyword));
		}
		return $this->process($this->request($query));
	}

	/**
	 * Get long URL of a shorturl
	 *
	 * @param string $shorturl to expand (can be either 'abc' or 'http://site/abc')
	 * @param string $format [optional] either "json" or "xml"
	 */
	public function expand($shorturl, $format = null)
	{
		if (empty($format)) {
			$format = $this->format;
		}
		$query = array(
			'action' => 'expand',
			'shorturl' => $shorturl,
			'format' => $format
		);
		return $this->process($this->request($query));
	}

	/**
	 * Get stats about one short URL
	 *
	 * @param string $shorturl for which to get stats (can be either 'abc' or 'http://site/abc')
	 * @param string $format [optional] either "json" or "xml"
	 */
	public function url_stats($shorturl, $format = null)
	{
		if (empty($format)) {
			$format = $this->format;
		}
		$query = array(
			'action' => 'url-stats',
			'shorturl' => $shorturl,
			'format' => $format
		);
		return $this->process($this->request($query));
	}

	/**
	 * Get stats about your links
	 *
	 * @param string $filter [optional] either "top", "bottom" , "rand" or "last"
	 * @param int [optional] $limit maximum number of links to return
	 * @param string $format [optional] either "json" or "xml"
	 */
	public function stats($filter = null, $limit = null, $format = null)
	{
		if (empty($format)) {
			$format = $this->format;
		}
		if (empty($filter)) {
			$filter = $this->filter;
		}
		$query = array(
			'action' => 'stats',
			'filter' => $filter,
			'format' => $format
		);
		if (!empty($limit)) {
			$query = array_merge($query, array('limit' => $limit));
		}
		return $this->process($this->request($query));
	}

}