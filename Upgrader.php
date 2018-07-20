<?php
use _ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class _Upgrader extends _Upgrader_Base {

  public function upgrade_0001() {
    $this->ctx->log->info('Applying update 0001');

    $result = civicrm_api3('Job', 'create', array(
      'run_frequency' => 'Weekly',
      'name' => 'Delete Twilio SMS Archives',
      'api_entity' => 'Sms',
      'api_action' => 'Deletetwiliocopy',
      'is_active' => 0,
    ));
  }

}
