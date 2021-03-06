<?php
/**
 * YiiDebugToolbarRouter class file.
 *
 * @author Sergey Malyshev <malyshev.php@gmail.com>
 */

namespace Panoptik\yiidebug;

use CEvent;
use CLogRoute;
use CWebApplication;
use Panoptik\yiidebug\YiiDebugToolbar;
use Yii;


/**
 * YiiDebugToolbarRouter represents an ...
 *
 * Description of YiiDebugToolbarRouter
 *
 * @author Sergey Malyshev <malyshev.php@gmail.com>
 * @version $Id$
 * @package YiiDebugToolbar
 * @since 1.1.7
 *
 * @property bool $enabled @see https://github.com/yiiext/yii/blob/master/framework/logging/CLogRoute.php#L37
 */
class YiiDebugToolbarRoute extends CLogRoute
{

    private $_panels = array(
        //'YiiDebugToolbarPanelServer',
        '\Panoptik\yiidebug\panels\YiiDebugToolbarPanelRequest',
        '\Panoptik\yiidebug\panels\YiiDebugToolbarPanelSettings',
        '\Panoptik\yiidebug\panels\YiiDebugToolbarPanelViews',
        '\Panoptik\yiidebug\panels\YiiDebugToolbarPanelSql',
        '\Panoptik\yiidebug\panels\YiiDebugToolbarPanelLogging',
    );

    /**
     * The filters are given in an array, each filter being:
     * - a normal IP (192.168.0.10 or '::1')
     * - an incomplete IP (192.168.0.* or 192.168.0.)
     * - a CIDR mask (192.168.0.0/24)
     * - "*" for everything.
     */
    public $ipFilters=array('127.0.0.1','::1');

    /**
     * Whitelist for response content types. DebugToolbarRoute won't write any
     * output if the server generates output that isn't listed here (json, xml,
     * files, ...)
     * @var array of content type strings (in lower case)
     */
    public $contentTypeWhitelist = array(
      // Yii framework doesn't seem to send content-type header by default.
      '',
      'text/html',
      'application/xhtml+xml',
    );

    /**
     * RegExp allowed user agent pattern without delimeters
     * @var string
     */
    public $allowedUserAgentPattern = 'Mozilla|Chrome|Safari|Opera';

    private $_toolbarWidget,
            $_startTime,
            $_endTime;


    private $_proxyMap = array(
        'viewRenderer' => '\Panoptik\yiidebug\YiiDebugViewRenderer'
    );

    public function setPanels(array $pannels)
    {
        $selfPanels = array_fill_keys($this->_panels, array());
        $this->_panels = array_merge($selfPanels, $pannels);
    }

    public function getPanels()
    {
        return $this->_panels;
    }

    public function getStartTime()
    {
        return $this->_startTime;
    }

    public function getEndTime()
    {
        return $this->_endTime;
    }

    public function getLoadTime()
    {
        return ($this->endTime-$this->startTime);
    }

    /**
     * @return YiiDebugToolbar
     * @throws \CException
     */
    protected function getToolbarWidget()
    {
        if (null === $this->_toolbarWidget)
        {
            $this->_toolbarWidget = Yii::createComponent(array(
                'class'=>'\Panoptik\yiidebug\YiiDebugToolbar',
                'panels'=> $this->panels
            ), $this);
        }
        return $this->_toolbarWidget;
    }

    public function init()
    {
        Yii::app()->controllerMap = array_merge(array(
        	'debug' => array(
        		'class' => '\Panoptik\yiidebug\YiiDebugController'
        	)
        ), Yii::app()->controllerMap);
        
        Yii::setPathOfAlias('yii-debug-toolbar', dirname(__FILE__));
        
        $route = Yii::app()->getUrlManager()->parseUrl(Yii::app()->getRequest());
        
        $this->enabled = strpos(trim($route, '/'), 'debug') !== 0;

        $this->enabled && $this->enabled = ($this->allowIp(Yii::app()->request->userHostAddress)
                && !Yii::app()->getRequest()->getIsAjaxRequest() && (Yii::app() instanceof CWebApplication)
        		&& $this->checkContentTypeWhitelist()&& $this->checkUserAgent());

        if ($this->enabled) {
            Yii::app()->attachEventHandler('onBeginRequest', array($this, 'onBeginRequest'));
            Yii::app()->attachEventHandler('onEndRequest', array($this, 'onEndRequest'));
            
            $this->categories = '';
            $this->levels='';
        }
        
        $this->_startTime = microtime(true);
        
        parent::init();
    }

    protected function onBeginRequest(CEvent $event)
    {
        $this->initComponents();

        $this->getToolbarWidget()
             ->init();
    }

    protected function initComponents()
    {
        foreach ($this->_proxyMap as $name=>$class) {
            $instance = Yii::app()->getComponent($name);
            if (null !== ($instance)) {
                Yii::app()->setComponent($name, null);
            }
            
            $this->_proxyMap[$name] = array(
                'class'=>$class,
                'instance' => $instance
            );
        }
        
        Yii::app()->setComponents($this->_proxyMap, false);
    }


    /**
     * Processes the current request.
     * It first resolves the request into controller and action,
     * and then creates the controller to perform the action.
     */
    private function processRequest()
    {
        if (is_array(Yii::app()->catchAllRequest) && isset(Yii::app()->catchAllRequest[0])) {
            $route = Yii::app()->catchAllRequest[0];
            foreach(array_splice(Yii::app()->catchAllRequest,1) as $name=>$value)
                $_GET[$name] = $value;
        } else {
            $route = Yii::app()->getUrlManager()->parseUrl(Yii::app()->getRequest());
        }
            
        Yii::app()->runController($route);
    }

    protected function onEndRequest(CEvent $event)
    {
    	$this->_endTime = microtime(true);
    }

	public function collectLogs($logger, $processLogs=false)
    {
        $logs = $logger->getLogs();
        $this->logs = empty($this->logs) ? $logs : array_merge($this->logs, $logs);
        $this->processLogs($this->logs);
        $this->logs = array();
    }

    protected function processLogs($logs)
    {
        if($this->enabled) {
            $this->getToolbarWidget()->run();
        }
    }

    private function checkContentTypeWhitelist()
    {
      $contentType = '';

      foreach (headers_list() as $header) {
        list($key, $value) = explode(':', $header);
        $value = ltrim($value, ' ');
        if (strtolower($key) === 'content-type') {
          // Split encoding if exists
          $contentType = explode(";", strtolower($value));
          $contentType = current($contentType);
          break;
        }
      }

      return in_array( $contentType, $this->contentTypeWhitelist );
    }

    /**
     * Checks to see if the user IP is allowed by {@link ipFilters}.
     * @param string $ip the user IP
     * @return boolean whether the user IP is allowed by {@link ipFilters}.
     */
    protected function allowIp($ip)
    {
        foreach ($this->ipFilters as $filter)
        {
            $filter = trim($filter);
            // normal or incomplete IPv4
            if (preg_match('/^[\d\.]*\*?$/', $filter)) {
                $filter = rtrim($filter, '*');
                if (strncmp($ip, $filter, strlen($filter)) === 0)
                {
                    return true;
                }
            }
            // CIDR
            else if (preg_match('/^([\d\.]+)\/(\d+)$/', $filter, $match))
            {
                if (self::matchIpMask($ip, $match[1], $match[2]))
                {
                    return true;
                }
            }
            // IPv6
            else if ($ip === $filter)
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an IP matches a CIDR mask.
     *
     * @param integer|string $ip IP to check.
     * @param integer|string $matchIp Radical of the mask (e.g. 192.168.0.0).
     * @param integer $maskBits Size of the mask (e.g. 24).
     * @return bool
     */
    protected static function matchIpMask($ip, $maskIp, $maskBits)
    {
        $mask =~ (pow(2, 32-$maskBits)-1);
        if (false === is_int($ip))
        {
            $ip = ip2long($ip);
        }
        if (false === is_int($maskIp))
        {
            $maskIp = ip2long($maskIp);
        }
        if (($ip & $mask) === ($maskIp & $mask))
        {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check User Agent. Should be a browser user agent. Otherwise debug info should not shown
     * @return bool
     */
    protected function checkUserAgent()
    {
        return preg_match('~' . $this->allowedUserAgentPattern . '~', Yii::app()->request->getUserAgent());
    }
}
