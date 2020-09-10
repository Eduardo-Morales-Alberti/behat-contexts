<?php

namespace Metadrop\Behat\Context;

use Drupal\Core\Url;
use Drupal\Driver\Cores\Drupal7;
use Metadrop\Behat\Cores\Traits\ScenarioTimeTrait;
use Behat\Testwork\Tester\Result\TestResults;
use Behat\Behat\Hook\Scope\AfterScenarioScope;

/**
 * Context used to work with logs.
 *
 * @package Metadrop\Behat\Context
 */
class LogsContext extends RawDrupalContext {

  use ScenarioTimeTrait;

  /**
   * Tests base url.
   *
   * @var string|null
   */
  protected $baseUrl;

  /**
   * LogsContext constructor.
   *
   * @param array $parameters
   *   Parameters (optional).
   */
  public function __construct(array $parameters = [])
  {
    if (isset($parameters['base_url'])) {
      $this->baseUrl = $parameters['base_url'];
    }
  }

  /**
   * Show watchdog logs messages after scenario.
   *
   * @param AfterScenarioScope $scope
   *   After Scenario scope.
   *
   * @AfterScenario @api
   */
  public function showDbLog(AfterScenarioScope $scope) {
    $module_is_enabled = in_array('dblog', $this->getCore()->getModuleList());

    if ($module_is_enabled) {
      $log_types = $scope->getTestResult()->getResultCode() === TestResults::PASSED ? ['php'] : [];
      // Filter by error, notice, and warning severity.
      $logs = $this->getCore()->getDbLogMessages($this->getScenarioStartTime(), [3, 4, 5], $log_types);
      if (!empty($logs)) {
        $this->printWatchdogLogs($logs);
      }
    }
  }

  /**
   * Print logs from watchdog.
   *
   * @param array $logs
   *   List of objects containing the message, the type, and the variables.
   */
  public function printWatchdogLogs(array $logs) {
    print 'Logs from watchdog (dblog):' . PHP_EOL . PHP_EOL;
    foreach ($logs as $log) {
      $log_variables = unserialize($log->variables);
      $log->variables = !empty($log_variables) ? $log_variables : [];
      $formatted_string =  $this->getCore()->formatString($log->message, $log->variables);
      $message = mb_strimwidth($formatted_string, 0, 200, '...');
      print "[{$log->type}] "
          . $message
          . " | Details: " . $this->getDblogEventUrl($log->wid) . "\n";
    }
    print "End of watchdog logs.";
  }

  /**
   * Get the log event url.
   *
   * @param int $wid
   *   Watchdog id.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   Generated url.
   */
  protected function getDblogEventUrl(int $wid) {
    $options = ['absolute' => TRUE];
    if (!empty($this->baseUrl)) {
      $options['base_url'] = $this->baseUrl;
    }

    // It is not possible to invoke core methods because the way the url generated is not compatible:
    // - In Drupal 7 it's used the relative path.
    // - In Drupal 8 it's used the routing system.
    if ($this->getCore() instanceof Drupal7) {
      return url('/admin/reports/event/' . $wid, $options);
    }
    else {
      return Url::fromRoute('dblog.event', ['event_id' => $wid], $options)->toString();
    }

  }

}
