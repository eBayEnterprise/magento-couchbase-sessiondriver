<?php
/**
 * Copyright (c) 2015-2016 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Derivative work based on original work created by: Magento
 * @copyright   Copyright (c) 2006-2015 X.commerce, Inc. (http://www.magento.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Changes by: eBay Enterprise, Inc.
 * @category    Mage
 * @package     Mage_Core
 * @author      Robert Krule <rkrule@ebay.com>
 * @createdate  December 2, 2015
 * @copyright   Copyright (c) 2015-2016 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
**/

class Mage_Core_Model_Session_Abstract_CouchbaseSession implements SessionHandlerInterface {
   /* Bots get shorter session lifetimes */
   /* TODO: Make this a configurable option in ADMIN and auto-detect bots -rkrule */
    const BOT_REGEX          = '/^alexa|^blitz\.io|bot|^browsermob|crawl|^curl|^facebookexternalhit|feed|google web preview|^ia_archiver|indexer|^java|jakarta|^libwww-perl|^load impact|^magespeedtest|monitor|^Mozilla$|nagios|^\.net|^pinterest|postrank|slurp|spider|uptime|crawler|find|NodeUptime|org_bot|QuerySeekSpider|GoogleBot|dnshealthcheckbot|UNDEFINED|bingbot|CCBot|^wget|yandex/i';
	
    const DEFAULT_FIRST_LIFETIME        = 600;      /* The session lifetime for non-bots on the first write */
    const DEFAULT_BOT_FIRST_LIFETIME    = 60;       /* The session lifetime for bots on the first write */

    const DEFAULT_LIFETIME		= 28880;    /* Default Lifetime after first write */
    const DEFAULT_BOT_LIFETIME          = 7200;     /* Lifetime for bots - shorter to prevent bots from wasting backend storage */

    const SLEEP_TIME         		= 500000;   /* Sleep 0.5 seconds between lock attempts (1,000,000 == 1 second) */
    const FAIL_AFTER         		= 15;       /* Try to break lock for at most this many seconds */

   /**
    * Holds the Couchbase connection.
    */
    protected $_connection = null;
 
    /**
    * The Couchbase host
    */
    protected $_host = null;

    /**
    * The Couchbase Cluster Username
    */
    protected $_user = null;

    /**
    * The Couchbase Cluster Password
    */
    protected $_password = null;

    /**
    * The couchbase port
 
    /**
    * The Couchbase bucket name.
    */
    protected $_bucket = null;
 
    /**
    * Define a expiration time by type of user-agent.
    */
    protected $_expire = null;

    /**
    * Set the default configuration params on init.
    */
    public function __construct( $host = 'couchbase://127.0.0.1?config_cache=/tmp/phpcb_cache&http_poolsize=10', $user = 'admin', $password = 'Welcome123', $bucket = 'session') {
        $this->_host = $host;
	$this->_user = $user;
	$this->_password = $password;
        $this->_bucket = $bucket;
    }
 
    /**
    * Open the connection to Couchbase (called by PHP on `session_start()`)
    */
    public function open($savePath, $sessionName) {
        $cluster = new CouchbaseCluster($this->_host, $this->_user, $this->_password);
        $this->_connection = $cluster->openBucket($this->_bucket);
	$this->_connection->__set('operationTimeout', 2000000); // 100000 = 1 second
	return $this->_connection ? true : false;
    }
 
    /**
    * Close the connection. Called by PHP when the script ends.
    */
    public function close() {
        unset($this->_connection);
        return true;
    }
 
    /**
    * Read data from the session.
    */
    public function read($sessionId) {
        $key = $sessionId;

	$result = null;

	//Spinlock on read several times....

	while(true)
	{
		try
		{
      			$result = $this->_connection->get($key)->value;
			break;
		} catch( CouchbaseException $e) {
			if( strpos( $e->getMessage(), "The key does not exist on the server") === false )
			{
				if( $try === self::FAIL_AFTER ) 
                        	{
				      $result = false;
                        	      break;
                       	}

				Mage::Log("In Try: ". $try . " attempting to obtain read for session key: ". $key . " sleeping: ". self::SLEEP_TIME . "(ms)");

                      		usleep(self::SLEEP_TIME);
                    		$try++; 
                  		continue;
			} else {
				//Key doesn't exist, just fail
				$result = false;
				break;
			}
		};
	}

        return $result;    
    }
 
    /**
    * Write data to the session.
    */
    public function write($sessionId, $sessionData) {
        $key = $sessionId;
	$keys = array( $key );

        if(empty($sessionData)) {
            return false;
        }

	$new = null;
	$try = 0;

	try
	{
		$new = $this->_connection->get($key);
	} catch( CouchbaseException $e) {
		//Do nothing....
	};

	if ( $this->_expire === null )
	{
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		
		if( strcmp( $userAgent, '') !== 0 )
		{
                	$isBot = ! $userAgent || preg_match(self::BOT_REGEX, $userAgent);
                	if ($isBot) {
				if ( $new == null ) // First Write
				{
					$this->_expire = self::DEFAULT_BOT_FIRST_LIFETIME;
					Mage::Log("First Write for Bot detected for user agent: ". $userAgent);
				} else { 
					$this->_expire = self::DEFAULT_BOT_LIFETIME;
                        		Mage::Log("Bot detected for user agent: ". $userAgent);
				}
                	} else {
				if ( $new == null ) // First Write Non Bot
				{
					$this->_expire = self::DEFAULT_FIRST_LIFETIME;
				} else {
					$this->_expire = self::DEFAULT_LIFETIME;
				}
			}
		} else {
			if ( $new == null ) // First Write Non Bot
                        {
                        	$this->_expire = self::DEFAULT_FIRST_LIFETIME;
                        } else {
                                $this->_expire = self::DEFAULT_LIFETIME;
                        }
		}
	}

	try
	{
        	$this->_connection->upsert($key, $sessionData, array('expiry' => $this->_expire ));
		$result = true;
	} catch( CouchbaseException $e ) {
		$result = false;
	};

        return $result;
    }

    /**
    * Delete data from the session.
    */
    public function destroy($sessionId) {
        $keys = array( $sessionId );
	$result = true;

	try
	{
        	$result = $this->_connection->remove($keys);
		$result = true;
        } catch( CouchbaseException $e) {
                $result = false;
        };

        return $result;
    }
 
    /**
    * Run the garbage collection.
    */
    public function gc($maxLifetime) {
        return true;
    }
}
?>
