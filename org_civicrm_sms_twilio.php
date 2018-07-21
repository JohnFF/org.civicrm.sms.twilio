<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.2                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2012                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

// Load the official Twilio library
require_once 'Services/Twilio/autoload.php';
use Twilio\Rest\Client;

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2012
 * $Id$
 *
 */
class org_civicrm_sms_twilio extends CRM_SMS_Provider {

  /**
   * api type to use to send a message
   * @var	string
   */
  protected $_apiType = 'http';

  /**
   * provider details
   * @var	string
   */
  protected $_providerInfo = array();

  public $_apiURL = "https://api.twilio.com/";

  protected $_messageType = array(
  );

  protected $_messageStatus = array(
  );

  /**
   * Twilio client object
   * @var Service_Twilio
   */
  protected $_twilioClient = null;

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var object
   * @static
   */
  static private $_singleton = array();

  /**
   * Constructor
   *
   * Create and auth a Twilio session.
   * This is not needed for Twilio
   *
   * @return void
   */
  function __construct($provider = array(
     ), $skipAuth = TRUE) {
    // initialize vars
    $this->_apiType = CRM_Utils_Array::value('api_type', $provider, 'http');
    $this->_providerInfo = $provider;

    // Instantiate the Twilio client
    if ($this->_apiType == 'http' &&
        array_key_exists('username', $this->_providerInfo) &&
        array_key_exists('password', $this->_providerInfo)
    ) {
      $sid = $this->_providerInfo['username'];
      $token = $this->_providerInfo['password'];
      $this->_twilioClient = new Client($sid, $token);
    }

    if ($skipAuth) {
      return TRUE;
    }

    $this->authenticate();
  }

  /**
   * singleton function used to manage this object
   *
   * @return object
   * @static
   *
   */
  static function &singleton($providerParams = array(
    ), $force = FALSE) {
    $providerID = CRM_Utils_Array::value('provider_id', $providerParams);
    $skipAuth   = $providerID ? FALSE : TRUE;
    $cacheKey   = (int) $providerID;

    if (!isset(self::$_singleton[$cacheKey]) || $force) {
      $provider = array();
      if ($providerID) {
        $provider = CRM_SMS_BAO_Provider::getProviderInfo($providerID);
      }
      self::$_singleton[$cacheKey] = new org_civicrm_sms_twilio($provider, $skipAuth);
    }
    return self::$_singleton[$cacheKey];
  }

  /**
   * Authenticate to the Twilio Server.
   * Not needed in Twilio
   * @return boolean TRUE
   * @access public
   * @since 1.1
   */
  function authenticate() {
      return (TRUE);
  }

  /**
   * Send an SMS Message via the Twilio API Server
   *
   * @param array the message with a to/from/text
   *
   * @return mixed SID on success or PEAR_Error object
   * @access public
   */
  function send($recipients, $header, $message, $jobID = NULL, $userID = NULL) {
    if ($this->_apiType == 'http') {
      $from = '';
      if (array_key_exists('From', $this->_providerInfo['api_params'])) {
        $from = $this->_providerInfo['api_params']['From'];
        if (preg_match('/\|/', $from)) {
          $froms = explode('|', $from);
          $key = array_rand($froms);
          $from = $froms[$key];
        }

      }

      try {
        $twilioMessage = $this->_twilioClient->messages->create(
          $header['To'],
          array(
            'from' => $from,
            'body' => $message
          )
        );
      } catch (Exception $e) {
        $errMsg = $e->getMessage();
        return PEAR::raiseError(
          $errMsg,
          $e->getCode(),
          PEAR_ERROR_RETURN
        );
      }

      $sid = $twilioMessage->sid;
      $this->createActivity($sid, $message, $header, $jobID, $userID);
      return $sid;
    }
  }

  function callback() {
  	return TRUE;
  }

  function inbound() {
    $like      = "";
    $fromPhone = $this->retrieve('From', 'String');
    return parent::processInbound($fromPhone, $this->retrieve('Body', 'String'), NULL, $this->retrieve('SmsSid', 'String'));
  }

  /**
   * Delete Twilio's copy of all SMS messages, both inbound and outbound, before
   * that date.
   *
   * @param int $allowedAgeInMonths
   * @param int $maxToDelete
   */
  public function deleteTwilioCopiesBeforeDate($allowedAgeInMonths, $maxToDelete) {
    $targetDateRaw = date('m/d/Y', strtotime('-' . $allowedAgeInMonths . ' months'));
    $targetDate = new DateTime($targetDateRaw);

    $getSmsActivityIdsToDelete = 'SELECT civicrm_activity.id, civicrm_activity.result
      FROM civicrm_activity LEFT JOIN civicrm_value_twilio_sent_s_29 ON civicrm_activity.id = civicrm_value_twilio_sent_s_29.entity_id
      WHERE activity_type_id IN (44, 45) AND
        (deleted_provider_copy_135 = 0 OR deleted_provider_copy_135 IS NULL) AND
        activity_date_time <= %1
      LIMIT %2';

    $activitiesToDelete = CRM_Core_DAO::executeQuery($getSmsActivityIdsToDelete, array(
      1 => array($targetDate->format('Y-m-d H:i:s'), 'String'),
      2 => array($maxToDelete, 'Int'),
    ));

    $messagesDeleted = 0;

    while ($activitiesToDelete->fetch()) {
      $twilioId = $activitiesToDelete->result;
      $responses[] = $this->_twilioClient->messages($twilioId)->delete();
      $messagesDeleted++;
      civicrm_api3('Activity', 'create', array(
        'id' => $activitiesToDelete->id,
        'custom_135' => 1,
      ));
    }
//    $activitiesToDelete = civicrm_api3('Activity', 'get', array(
//      'count' => $maxToDelete,
//      'activity_type_id' => array('IN' => array('SMS', 'Inbound SMS')),
//      'activity_date_time' => array('<=' => $targetDate),
//     // 'custom_135' => '0',
//      'return' => array('result'),
//    ));
//
//    foreach($activitiesToDelete['values'] as $eachActivityToDelete) {
//      $this->_twilioClient->messages($eachActivityToDelete['result'])->delete();
//      civicrm_api3('Activity', 'create', array(
//        'id' => $eachActivityToDelete['id'],
//        'custom_135' => 1,
//      ));
//    }

    return $messagesDeleted;
  }

}
