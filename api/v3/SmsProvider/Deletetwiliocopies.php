<?php
use _ExtensionUtil as E;

/**
 * SmsProvider.Deletetwiliocopies API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sms_provider_Deletetwiliocopies_spec(&$spec) {
  $spec['provider_id']['api.required'] = 1;
  $spec['allowed_age_in_months']['api.required'] = 1;
  $spec['max_to_delete']['api.required'] = 0;
}

/**
 * SmsProvider.Deletetwiliocopies API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_sms_provider_Deletetwiliocopies($params) {
  $maxToDelete = $params['max_to_delete'] ? $params['max_to_delete'] : 25;

  require_once '/srv/www/krystalclone/sites/default/files/civicrm/ext/org.civicrm.sms.twilio/org_civicrm_sms_twilio.php';

  $twilio = org_civicrm_sms_twilio::singleton($params);
  $twilio->deleteTwilioCopiesBeforeDate($params['allowed_age_in_months'], $maxToDelete);
}
