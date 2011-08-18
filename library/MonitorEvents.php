<?php
require_once("framework/api/zte.inc");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_LONGSCRIPT.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_DEVSCRIPT.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_LONGFUNCTION.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_SLOWCONTENTDOWNLOAD.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_LONGQUERY.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_PARSEERROR.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_NOTICEERROR.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_FUNCERROR.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_DBERROR.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_MEMSIZE.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_DEVMEM.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_OUTPUT.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_CUSTOM.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_ERROR_REPORTING.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_SILENCE_LEVEL.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_MAXAPACHEPROCESSES.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_JAVAEXCEPTION.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_HTTPERROR.php");
require_once("/usr/local/zte3/tests/platform/new_monitoring/tests/api/res/V_SBL.php");

///////////////////////////////// Globals //////////////////////////

///////////////////////////////// Steps ///////////////////////////
abstract class StepBase extends ZteTestSubStep {
	protected $verifier;
	protected $name;
	protected $script_name;
	protected $script_params;
	protected $received_event;
	protected $ABCom1;
	protected $ABCom2;
	
	
	public function doSetup() {
		TEF::addResourceFileToDocRoot($this->script_name);
		TEF::addResourceFileToDocRoot('get_all_events.php');
		TEF::addResourceFileToDocRoot('delete_all_events.php');
		TEF::addResourceFileToDocRoot('is_monitor_exist.php');
		TEF::addResourceFileToDocRoot('set_aggrgation_hint.php');
		// disable acceleration as it causes apache segfaults
		$this->setZendIniDirective("zend_accelerator.enable", '0' );

		TEF::webserverRestart();
		// check that monitor is loaded. if not stopping the test.
		if (!TEF::wwwGetLocalhost('is_monitor_exist.php')) {
			self::setFail(self::SFAIL_Test, "Step: ".$test_class_name.": Monitor is not loaded. Stopping TEST.");
		}
		//Remove previous events from DB
		//$this->verifier->removeAllEvents();
		if (!TEF::wwwGetLocalhost('delete_all_events.php')) {
			self::setFail(self::SFAIL_Test, "Step ". $test_class_name. " Failed to remove events from DB. Stopping TEST.");
		}
		//setting aggregation hint for each step to prevent aggregation because of bug #19432
		TEF::wwwGetLocalhost("set_aggregation_hint.php?hint=$test_class_name");
		return true;
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		TEF::wwwGetLocalhost($this->script_name, $this->script_params);
				 
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
	
	protected function isPassed() {
		$this->logger->funcStart();
		
		//running API 'get_all_events': expecting only one item in the returned array (i.e. one event).
		$result = unserialize(TEF::wwwGetLocalhost('get_all_events.php'));
		$this->received_event = $result;
//		if (count($result) === 0) {
//			self::setFail(self::SFAIL_Step, "Step ".$test_class_name." Failed. Received an empty results array.");
//		}
		
		$result = $this->verifier->isPassed($this->received_event);
		$test_class_name = get_class($this);
		if ($result === true) {
			echo "Success for {$test_class_name} ++++++++++++++++++++++++++++++++++++++++\n";
			return true;
		} else {
			echo "Failure {$test_class_name} ----------------------------------------\n";
			//$this->verifier->dumpAllTables(); //printing table contentn			//$this->verifier->dumpEventsArray();
			echo '<pre>';
			var_dump($this->received_event);
			self::setFail(self::SFAIL_Step,"Step ".$test_class_name." Failed. Continuing to next step.");
		}
//		return $result;
	}
	
	protected function createFailedReport() {
		$test_class_name = get_class($this);
		return new ZteStepFailedReport(
			ZteStepFailedReport::createMinReport( "{$test_class_name} failed", 
								$this->verifier->getErrorDesc(), 
								$this->verifier->getTodo() ) );
	}
	
	protected function setZendIniDirective($directive_name, $directive_value) {
		if (TEF::zendIniGetEntry($directive_name) != null) {
			TEF::zendIniRemoveEntry($directive_name);
		}

		TEF::zendIniAddEntry($directive_name, $directive_value);
	}
	
	protected function setPhpIniDirective($directive_name, $directive_value) {
		if (TEF::phpIniGetEntry($directive_name) != null) {
			TEF::phpIniRemoveEntry($directive_name);
		}

		TEF::phpIniAddEntry($directive_name, $directive_value);
	}

/**
 * enableActiveEvents 
 * enables only the events received in $active_events and disables all other events
 */
	protected function enableActiveEvents($active_events) {
		$events = array(
				"longscript",
				"longfunction",
				"zenderror",
				"devscript",
				"funcerror",
				"devmem",
				"outsize",
				"memsize",
				"load",
				"custom",
				"slowcontentdownload",
				"maxapacheprocesses",
				"javaexception",
				"httperror"
);

		foreach($events as $event){
			$this->setZendIniDirective("zend_monitor.{$event}.enable", 'no' );
		}

		// convert active events to an array if it isnt already
		if(!is_array( $active_events ) ){
			$active_events = array( $active_events );
		}

		foreach($active_events as $active_event){
			if(!is_null($active_event)){
				$this->setZendIniDirective("zend_monitor.{$active_event}.enable", 'yes' );
			}
		}


	}
	
	// Used to overcome the seg fault problems in apache.
	// Since wget has retry mechanism, it overcomes the problem.
	protected function wget() {
		$url = "http://localhost/";
		$url .= $this->script_name;
		$params = $this->script_params;
		if (count($params) > 0) {
			$url .= '?';
			foreach($params as $param) {
				$url .= $param . '&';
			}
		}

		`wget -O {$this->script_name} {$url}`;
	}
}

/**
 * LONGSCRIPT_basic 
 *
 */

final class LONGSCRIPT_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__, 
		ZteStepDescriptor::createMinDescriptor('LONGSCRIPT produced when script running longer than 500ms and less then 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->script_params = array('sleep'=>'1'); 
		$this->verifier = new V_LONGSCRIPT_eventGenerated(900/*ms*/, '1', '1'); //Limor: changed 3rd arg: repeat count from 0 to 1

		$this->enableActiveEvents("longscript");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "no");

		return parent::doSetup();
	}
}

/**
 * LONGSCRIPT_severe 
 *
 */

final class LONGSCRIPT_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGSCRIPT produced with severe flag when script running longer than 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->script_params = array('sleep'=>'3');
		$this->verifier = new V_LONGSCRIPT_eventGenerated(2900/*ms*/, '2', '1'); //Limor: changed 3rd arg: repeat count from 0 to 1
		
		$this->enableActiveEvents("longscript");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "no");
		
		return parent::doSetup();
	}
}

/**
 * LONGSCRIPT_disabled 
 *
 */

final class LONGSCRIPT_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGSCRIPT NOT produced when script running longer than 500ms and less then 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->script_params = array('sleep'=>'1');
		$this->verifier = new V_LONGSCRIPT_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "no");
		
		return parent::doSetup();
	}
}

/**
 * DEVSCRIPT_basic 
 *
 */

final class DEVSCRIPT_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVSCRIPT produced when script runtime exceeds the avg by more than 10% and less than 60%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventGenerated(3000, 1000, '1'); //Limor: changed 3rd arg from 0 to 1: regular severity
		
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "60"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// The first 3 will cause an average of 1 sec
 		// The 4th will increase the average to 1.5 sec, which will cause a DEVSCRIPT event
// 		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 3));
		
		//the params for setting the average runtime of a scipt
		$this->ABCom1 = array(
				'requests_num' => 500, 
				'concurrency' => 100,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=1"
				);
				
	//the params for changing the average of a script runtime.
		$this->ABCom2 = array(
				'requests_num' => 10,
				'concurrency' => 5,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=3"
		);
		
		//1st object to set the average runtime. 
		$set_avg = new TefApacheBenchmark();
		$res_avg = $set_avg->doExecute($this->ABCom1);
				
		//2nd object to change the average runtime
		$chg_avg = new TefApacheBenchmark();
		$res_chg_avg = $chg_avg->doExecute($this->ABCom2);
				
//		$ts = TEF::wwwGetLocalhost("time_mul.php");
//		sleep(2); 
//		$this->ab = new TefApacheBenchmark();
//		$res = $this->ab->doExecute($this->ABCom);
//		$this->output = array();
//		//$this->logger->debug($res);
//		$pattern = "/[0-9]{10}\.[0-9]{1,4}/m";
//		preg_match_all($pattern, $res, $this->outputs);
//		var_dump("This is ts: ".$ts);		

		
		sleep(1);
	}
}

/**
 * DEVSCRIPT_severe 
 *
 */

final class DEVSCRIPT_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVSCRIPT produced when script runtime exceeds the avg by more than 60'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventGenerated(4000, 1000, '2');
		
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "60"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// The first 3 will cause an averager of 1 sec
 		// The 4th will increase the average to 1.75 sec, which will cause a DEVSCRIPT event
//  		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 4));
		
		//the params for setting the average runtime of a scipt
		$this->ABCom1 = array(
				'requests_num' => 500, 
				'concurrency' => 100,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=1"
				);
				
		//the params for changing the average of a script runtime.
		$this->ABCom2 = array(
				'requests_num' => 10,
				'concurrency' => 5,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=4"
		);
		
		//1st object to set the average runtime. 
		$set_avg = new TefApacheBenchmark();
		$res_avg = $set_avg->doExecute($this->ABCom1);
		
		//2nd object to change the average runtime
		$chg_avg = new TefApacheBenchmark();
		$res_chg_avg = $chg_avg->doExecute($this->ABCom2);
				
		sleep(1);
	}
}

/**
 * DEVSCRIPT_threshold 
 *
 */

final class DEVSCRIPT_threshold extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVSCRIPT NOT produced when script runtime exceeds the avg by more than 10% and less than 60%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "60"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "10000"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// All 4 calls are less than the 5 sec threshold. 
		// Therfore no DEVSCRIPT event should be generated 
  		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 3));
		

	}
}

final class	DEVSCRIPT_disabled extends StepBase {
	
	
		
	public static function createStepDescriptor() {
			return new ZteStepDescriptor(__CLASS__, __FILE__,
			ZteStepDescriptor::createMinDescriptor('DEVSCRIPT NOT produced when script runtime exceeds the avg by more than 10% and less than 60%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
	
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "60"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// The first 3 will cause an averager of 1 sec
 		// The 4th will increase the average to 1.5 sec, which will cause a DEVSCRIPT event
		// BUT!! since DEVSCRIPT is DISABLED, it should not be produced
		
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1));
//		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 3));
		
		$this->ABCom1 = array(
				'requests_num' => 500, 
				'concurrency' => 100,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=1"
				);
		$this->ABCom2 = array(
				'requests_num' => 500, 
				'concurrency' => 100,
				'verbose' => 4,
				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=3"
				);
		
		//1st object to set the average runtime. 
		$set_avg = new TefApacheBenchmark();
		$res_avg = $set_avg->ab->doExecute($this->ABCom1);
		
		//2nd object to change the average runtime
		$chg_avg = new TefApacheBenchmark();
		$res_chg_avg = $chg_avg->ab->doExecute($this->ABCom2);
		
		sleep(1);
	}
}

/**
 * WARMUP_DEVSCRIPT_below
 * Verify that as long as we havn't pass the warmup limit, no DEVSCRIPT event is generated 
 *
 */

final class WARMUP_DEVSCRIPT_below extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that as long as we havn\'t pass the warmup limit, no DEVSCRIPT event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "1"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "3"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "3");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		// DEVSCRIPT event should not be genrated since the WARMUP is 3 and we have done only 3 calls
  		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1)); // 1
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 2)); // +100%
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 2)); // +33%
		
		
		
		sleep(1);
	}
}

/**
 * WARMUP_DEVSCRIPT_above
 * Verify that right after we reach the warmup limit DEVSCRIPT event is generated 
 * 3/5/2007 This TEST will fail because of an existing BUG! WARMUP is set to 2 but event generated only on the 3rd
 *
 */

final class WARMUP_DEVSCRIPT_above extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that just as we pass the warmup limit, DEVSCRIPT event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventGenerated(2000, 1000, '1');
		
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "200"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "1");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		// DEVSCRIPT event should be genrated since the WARMUP is 2 and we passed the limit
  		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1)); // 1
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 1)); // 1
		TEF::wwwGetLocalhost($this->script_name, array("sleep" => 2)); // 2 > 1 => +100%
		
		sleep(1);
	}
}

/**
 * LONGFUNCTION_basic 
 *
 */

final class LONGFUNCTION_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGFUNC produced when monitored func: goto_sleep, runs longer than 500ms and less than 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGFUNCTION.php';
		$this->script_params = array('sleep'=>'1');
 		$this->verifier = new V_LONGFUNCTION_eventGenerated(995, '1', 1); //changed severity (2nd argument) from 0 to 1

		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "goto_sleep");
		
		return parent::doSetup();
	}
}

/**
 * LONGFUNCTION_severe 
 *
 */



final class LONGFUNCTION_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGFUNC produced with severe flag, when monitored func: goto_sleep, runs longer than 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGFUNCTION.php';
		$this->script_params = array('sleep'=>'2'); //Itay: I give a 10% error margin to the 2000MS boundry 
		$this->verifier = new V_LONGFUNCTION_eventGenerated(1995, '2', 2); //changed expected severity (2nd argument) from 1 to 2
		
		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "1800");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "goto_sleep");
		
		return parent::doSetup();
	}
}

/**
 * LONGFUNCTION_disabled 
 *
 */

final class LONGFUNCTION_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGFUNC produced with severe flag, when monitored func: goto_sleep, runs longer than 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGFUNCTION.php';
		$this->script_params = array('sleep'=>'2');
		$this->verifier = new V_LONGFUNCTION_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "goto_sleep");
		
		return parent::doSetup();
	}
}


/**
 * LONGFUNCTION_notMonitoredFunc 
 *
 */

final class LONGFUNCTION_notMonitoredFunc extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGSCRIPT not produced when non-monitored func non_monitored_goto_sleep (not in \'empty_watch_funcs.txt\' file) run slower then 500ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGFUNCTION.php';
		$this->script_params = array('sleep'=>'1');
		$this->verifier = new V_LONGFUNCTION_eventNotGenerated($this->received_event);
		
		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "");

		return parent::doSetup();
	}
}


/**
 * LONGFUNCTION_enableLONGSCRIPT 
 *
 */

final class LONGFUNCTION_enableLONGSCRIPT extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify a LONGSCRIPT is generated together with LONGFUNCTION'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGFUNCTION.php';
		$this->script_params = array('sleep'=>'1');
		$this->verifier = new V_LONGFUNCTION_enableLONGSCRIPT();
		

		$this->enableActiveEvents(array('longfunction', 'longscript'));

		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); 
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "2000");		
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "no");
		$this->setZendIniDirective("zend_monitor.silence_level","0");
		$this->setZendIniDirective("zend_monitor.watch_functions", "goto_sleep");

		
		$this->setPhpIniDirective("error_reporting", "E_ALL");
	
		return parent::doSetup();
	}
	
}

/**
 * SLOWCONTENTDOWNLOAD_basic 
 *
 */

final class SLOWCONTENTDOWNLOAD_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('SLOWCONTENTDOWNLOAD produced when monitored fpassthrue, runs longer than 1ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SLOWCONTENTDOWNLOAD.php';
		$this->script_params = array('filename' => 'fpassthru_large_file');
		$this->verifier = new V_SLOWCONTENTDOWNLOAD_eventGenerated(5, 0);
	
		$this->enableActiveEvents('slowcontentdownload');
		$this->setZendIniDirective("zend_monitor.max_content_download_time", "1");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "fpassthru");

		TEF::addResourceFileToDocRoot('fpassthru_large_file');
		
		return parent::doSetup();
	}
}

/**
 * SLOWCONTENTDOWNLOAD_severe
 *
 */

final class SLOWCONTENTDOWNLOAD_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('severe SLOWCONTENTDOWNLOAD produced when monitored fpassthrue, runs longer than 3ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SLOWCONTENTDOWNLOAD.php';
		$this->script_params = array('filename' => 'fpassthru_large_file');
		$this->verifier = new V_SLOWCONTENTDOWNLOAD_eventGenerated(5, 1);
	
		$this->enableActiveEvents('slowcontentdownload');
		$this->setZendIniDirective("zend_monitor.max_content_download_time", "1");
		$this->setZendIniDirective("zend_monitor.max_content_download_time.severe", "3");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "fpassthru");

		TEF::addResourceFileToDocRoot('fpassthru_large_file');
		
		return parent::doSetup();
	}
}

/**
 * SLOWCONTENTDOWNLOAD_disabled
 *
 */

final class SLOWCONTENTDOWNLOAD_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('SLOWCONTENTDOWNLOAD NOT produced when monitored fpassthrue, runs longer than 1ms and zend_monitor.slowcontentdownload.enable = \'no\''));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SLOWCONTENTDOWNLOAD.php';
		$this->script_params = array('filename' => 'fpassthru_large_file');
		$this->verifier = new V_SLOWCONTENTDOWNLOAD_eventNotGenerated();
	
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_content_download_time", "1");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "fpassthru");

		TEF::addResourceFileToDocRoot('fpassthru_large_file');
		
		return parent::doSetup();
	}
}

/**
 * SLOWCONTENTDOWNLOAD_max_content_download
 *
 */

final class SLOWCONTENTDOWNLOAD_max_content_download extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('SLOWCONTENTDOWNLOAD NOT produced when monitored fpassthrue, runs less than 1000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SLOWCONTENTDOWNLOAD.php';
		$this->script_params = array('filename' => 'fpassthru_large_file');
		$this->verifier = new V_SLOWCONTENTDOWNLOAD_eventNotGenerated();
	
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.slowcontentdownload.enable", "yes");
		$this->setZendIniDirective("zend_monitor.max_content_download_time", "1000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", "fpassthru");

		TEF::addResourceFileToDocRoot('fpassthru_large_file');
		
		return parent::doSetup();
	}
}

/**
 * SLOWCONTENTDOWNLOAD_longfuncOnFpassthru 
 *
 */

final class SLOWCONTENTDOWNLOAD_longfuncOnFpassthru extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGFUNCTION produced when slowcontetndownload is disabled and monitored fpassthrue, runs longer than 500ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
	
		$this->script_name = 'SLOWCONTENTDOWNLOAD.php';
		$this->script_params = array('filename' => 'fpassthru_large_file');
		$this->verifier = new V_SLOWCONTENTDOWNLOAD_longfunctionEventGenerated(5);

		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "2");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", 'fpassthru'); 
	
		$this->setZendIniDirective("zend_monitor.max_content_download_time", "2");

		TEF::addResourceFileToDocRoot('fpassthru_large_file');
		
		return parent::doSetup();
	}
}




/**
 * LONGQUERY_basic 
 *
 */

final class LONGQUERY_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGQUERY produced when a DB related function runs longer than 500m and less then 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGQUERY.php';
		$this->script_params = array('sleep' => 1, 'sql_func_name' => 'mysql_longquery');
		$this->verifier = new V_LONGQUERY_eventGenerated('mysql_longquery', 995);
		
		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", 'mysql_longquery');

		return parent::doSetup();
	}
}

/**
 * LONGQUERY_withUnderscore 
 * this test was intruduced due to a bug in monitor
 */

final class LONGQUERY_withUnderscore extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('LONGQUERY produced when a DB related function, with underscore in its name, runs longer than 500m and less then 2000ms'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGQUERY.php';
		$this->script_params = array('sleep' => 1, 'sql_func_name' => 'mysql_long_query');
		$this->verifier = new V_LONGQUERY_eventGenerated('mysql_long_query', 995);
		
		$this->enableActiveEvents('longfunction');
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "yes");
		$this->setZendIniDirective("zend_monitor.watch_functions", 'mysql_long_query');
		
		return parent::doSetup();
	}
}



/**
 * ZENDERROR_parseerror 
 *
 */

final class ZENDERROR_parseerror extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR of type PARSEERROR is produced when a script with Syntax Error is executed'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'PARSEERROR.php';
		$this->verifier = new V_PARSEERROR_eventGenerated();
		
		$this->enableActiveEvents('zenderror');

		return parent::doSetup();
	}
}

/**
 * ZENDERROR_parseerror_disabled 
 *
 */

final class ZENDERROR_parseerror_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR of type PARSEERROR is NOT produced when a script with Syntax Error is executed'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'PARSEERROR.php';
		$this->verifier = new V_PARSEERROR_eventNotGenerated();
		
		$this->enableActiveEvents();

		return parent::doSetup();
	}
}

/**
 * ZENDERROR_noticeerror 
 *
 */

final class ZENDERROR_noticeerror extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR of type NOTICEERROR is produced when a script with Notice Error is executed'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'NOTICEERROR.php';
		$this->verifier = new V_NOTICEERROR_eventGenerated();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.error_reporting", "E_ALL & E_STRICT");

		return parent::doSetup();
	}
}

/**
 * ZENDERROR_noticeerror_disabled 
 *
 */

final class ZENDERROR_noticeerror_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR of type NOTICEERROR is NOT produced when a script with Notice Error is executed'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'NOTICEERROR.php';
		$this->verifier = new V_NOTICEERROR_eventNotGenerated();
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.error_reporting", "E_ALL & E_NOTICE");
		
		return parent::doSetup();
	}
}

abstract class FUNCERROR_papa extends StepBase {
	public function doSequence() {
		// We are calling wget becuase:
		// The calls to FUNCERROR.php causes seg fault and thus TEF::wwwGetLocalhost fail.
		// wget has retry mechanism and thus fail at the first time and succeeds at the second time (after the retry)
		// TODO fix the seg fault
		$this->wget();
		sleep(1);
	}
}
/**
 * FUNCERROR_basic 
 *
 */

final class FUNCERROR_basic extends FUNCERROR_papa {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('FUNCERROR produced when monitored func generate_func_error returns error'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'FUNCERROR.php';
		$this->script_params = array('function_name=generate_func_error');
		$this->verifier = new V_FUNCERROR_eventGenerated('generate_func_error');
		
		$this->enableActiveEvents('funcerror');
		$this->setZendIniDirective("zend_monitor.watch_results", 'generate_func_error');

		return parent::doSetup();
	}
}

/**
 * FUNCERROR_disabled 
 *
 */

final class FUNCERROR_disabled extends FUNCERROR_papa {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('FUNCERROR not produced when monitored func generate_func_error returns error'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'FUNCERROR.php';
		$this->script_params = array('function_name' => 'generate_func_error');
		$this->verifier= new V_FUNCERROR_eventNotGenerated();
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.watch_results", 'generate_func_error');
		
		return parent::doSetup();
	}
}

/**
 * FUNCERROR_notMonitored
 *
 */

final class FUNCERROR_notMonitored extends FUNCERROR_papa {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('FUNCERROR not produced when non-monitored func YYY (not in \'watch_res\' directive) return error'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'FUNCERROR.php';
		$this->script_params = array('function_name' => 'not_monitored_generate_func_error');
		$this->verifier= new V_FUNCERROR_eventNotGenerated();

		$this->enableActiveEvents('funcerror');
		$this->setZendIniDirective("zend_monitor.watch_results", 'generate_func_error');
		
		return parent::doSetup();
	}
}

/**
 * DBERROR_basic 
 *
 */

final class DBERROR_basic extends FUNCERROR_papa {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DBERROR produced when monitored func mysql_generatedberror returns error'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'DBERROR.php';
		$this->verifier= new V_DBERROR_eventGenerated();
		
		$this->enableActiveEvents('funcerror');
		$this->setZendIniDirective("zend_monitor.watch_results", 'mysql_generatedberror');

		return parent::doSetup();
	}
}

/**
 * DBERROR_disabled 
 *
 */

final class DBERROR_disabled extends FUNCERROR_papa {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DBERROR NOT produced when monitored func mysql_generatedberror returns error'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'DBERROR.php';
		$this->verifier= new V_DBERROR_eventNotGenerated();
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.watch_results", 'mysql_generatedberror');
		
		return parent::doSetup();
	}
}

/**
 * MEMSIZE_basic 
 *
 */

final class MEMSIZE_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MEMSIZE produced when script mem usage exceeds 100k and less than 300k'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->script_params = array('size' => 300);
		$this->verifier = new V_MEMSIZE_eventGenerated(100000, 0);
		
		$this->enableActiveEvents('memsize');
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "100");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "1000");
		return parent::doSetup();
	}
}

/**
 * MEMSIZE_severe 
 *
 */

final class MEMSIZE_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MEMSIZE produced with Severe flag when script mem usage exceeds 200k'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->script_params = array('size' => 1000);
		$this->verifier = new V_MEMSIZE_eventGenerated(200000, 1);
		
		$this->enableActiveEvents('memsize');
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "100");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "200");

		return parent::doSetup();
	}
}

/**
 * MEMSIZE_disabled 
 *
 */

final class MEMSIZE_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MEMSIZE NOT produced with Severe flag when script mem usage exceeds 300k'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->script_params = array('size' => 1000);
		$this->verifier = new V_MEMSIZE_eventNotGenerated();
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "200");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "300");

		return parent::doSetup();
	}
}

/**
 * DEVMEM_basic 
 *
 */

final class DEVMEM_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVMEM produced when script mem usage exceeds the avg by more than 10% and less than 50%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventGenerated('1'); //argument changed from 0 to 1 (normal severity)
		
		$this->enableActiveEvents('devmem');
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "50"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1200));
		
//		$this->ABCom1 = array(
//				'requests_num' => 100, 
//				'concurrency' => 10,
//				'verbose' => 4,
//				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=1000"
//				);
//				
//	//the params for changing the average of a script runtime.
//		$this->ABCom2 = array(
//				'requests_num' => 5,
//				'concurrency' => 5,
//				'verbose' => 4,
//				'url' => TEF::getLocalhostUrlPrefix()."/$this->script_name?sleep=1200"
//		);
//		
//		//1st object to set the average runtime. 
//		$set_avg = new TefApacheBenchmark();
//		$res_avg = $set_avg->doExecute($this->ABCom1);
//				
//		//2nd object to change the average runtime
//		$chg_avg = new TefApacheBenchmark();
//		$res_chg_avg = $chg_avg->doExecute($this->ABCom2);
		
		
		sleep(1);
	}
	
}

/**
 * DEVMEM_severe 
 *
 */

final class DEVMEM_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVMEM produced when script mem usage exceeds the avg by more than 50%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventGenerated(1);
		
		$this->enableActiveEvents('devmem');
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "50"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2500));
		
		sleep(1);
	}
	
}

/**
 * DEVMEM_disabled 
 *
 */

final class DEVMEM_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVMEM not produced when script mem usage exceeds the avg by more than 10% and less than 50%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventNotGenerated();
		
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "50"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1200));
		
		sleep(1);
	}
	
}

/**
 * DEVMEM_threshold 
 *
 */

final class DEVMEM_threshold extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVMEM not produced when script mem usage is less than 50%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventNotGenerated('0'); //argument change from 0 to 1 = normal severity
		
		$this->enableActiveEvents('devmem');
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "50"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "100000"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1200));
		
		sleep(1);
	}
	
}

/**
 * WARMUP_DEVMEM_below 
 *
 */

final class WARMUP_DEVMEM_below extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that as long as we havn\'t passed the warmup limit, no DEVMEM event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventNotGenerated();
		
		$this->enableActiveEvents('devmem');
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "1"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "3"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "3");
		
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// DEVMEM event should not be genrated since the WARMUP is 3 and we have done only 3 calls
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +100%
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +33%
		
		sleep(1);
	}
}

/**
 * WARMUP_DEVMEM_above 
 *
 */

final class WARMUP_DEVMEM_above extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that just as we pass the warmup limit, DEVMEM event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'MEMSIZE.php';
		$this->verifier = new V_DEVMEM_eventGenerated('1'); //argument changed from 0 to 1 (normal severity
		
		$this->enableActiveEvents('devmem');
		$this->setZendIniDirective("zend_monitor.max_mem_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_mem_dev.severe", "200"); 
		$this->setZendIniDirective("zend_monitor.mem_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// DEVMEM event should not be genrated since the WARMUP is 3 and we have done only 3 calls
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +100%
		
		sleep(1);
	}
}

/**
 * OUTPUT_basic 
 *
 */

final class OUTPUT_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('OUTPUT produced when script output exceeds the avg in 10% and less than 100%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventGenerated(2082, '1'); //Limor: 2nd arg (severity change from 0 to 1

		$this->enableActiveEvents('outsize');

		$this->setZendIniDirective("zend_monitor.max_output_dev", "10");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "100"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "1"); 
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1800));
		
		sleep(1);
	}
}

/**
 * OUTPUT_severe 
 *
 */

final class OUTPUT_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('OUTPUT produced when script output exceeds the avg by more than 100%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventGenerated(3330, 1);
		
		$this->enableActiveEvents('outsize');
		$this->setZendIniDirective("zend_monitor.max_output_dev", "10");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "100"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "1"); 
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 3000));
		
		sleep(1);
	}
	
}

/**
 * OUTPUT_disabled
 *
 */

final class OUTPUT_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('OUTPUT NOT produced when script output exceeds the avg in 10% and less than 100%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventNotGenerated();

		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.max_output_dev", "10");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "100"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "1"); 
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1800));
		
		sleep(1);
	}
	
}

/**
 * OUTPUT_threshold
 *
 */

final class OUTPUT_threshold extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('OUTPUT NOT produced when script output exceeds the avg in 10% and less than 100%'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventNotGenerated();

		$this->enableActiveEvents('outsize');
		$this->setZendIniDirective("zend_monitor.max_output_dev", "10");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "100"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "20"); 
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1800));
		
		sleep(1);
	}
	
}

/**
 * WARMUP_OUTPUT_below 
 *
 */

final class WARMUP_OUTPUT_below extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that as long as we havn\'t passed the warmup limit, no OUTPUT event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventNotGenerated();

		$this->enableActiveEvents('outsize');
		$this->setZendIniDirective("zend_monitor.max_output_dev", "1");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "3"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "1"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "3");
		
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +100% 
//		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000));
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +33%
		
		sleep(1);
	}
}

/**
 * WARMUP_OUTPUT_above
 * 3/5/2007 This TEST will fail because of an existing BUG! WARMUP is set to 2 but event generated only on the 3rd
 *
 */

final class WARMUP_OUTPUT_above extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that just as we pass the warmup limit, OUTPUT event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'OUTPUT.php';
		$this->verifier = new V_OUTPUT_eventGenerated(2000, 0);

		$this->enableActiveEvents('outsize');
		$this->setZendIniDirective("zend_monitor.max_output_dev", "1");
		$this->setZendIniDirective("zend_monitor.max_output_dev.severe", "200"); 
		$this->setZendIniDirective("zend_monitor.output_threshold", "1"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_function_runtime.severe", "5000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage", "2000000");
		$this->setZendIniDirective("zend_monitor.max_memory_usage.severe", "3000000");


		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000
		TEF::wwwGetLocalhost($this->script_name, array("size" => 1000)); // 1000 
		TEF::wwwGetLocalhost($this->script_name, array("size" => 2000)); // +100%
		
		sleep(1);
	}
}

/**
 * CUSTOM_basic 
 *
 */

final class CUSTOM_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that basic Custom event generation is working'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'CUSTOM.php';
		$this->script_params = array("severe" => "0");
		$this->verifier = new V_CUSTOM_eventGenerated(0);
		
		$this->enableActiveEvents('custom');
		
		return parent::doSetup();
	}
}

/**
 * CUSTOM_severe
 *
 */

final class CUSTOM_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that severe Custom event generation is working'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'CUSTOM.php';
		$this->script_params = array("severe" => "1");
		$this->verifier = new V_CUSTOM_eventGenerated(1);
		
		$this->enableActiveEvents('custom');
		
		return parent::doSetup();
	}
}


/**
 * CUSTOM_disabled 
 *
 */

final class CUSTOM_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that when Custom event is disabled no event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'CUSTOM.php';
		$this->script_params = array("severe" => "0");
		$this->verifier = new V_CUSTOM_eventNotGenerated();
		
		$this->enableActiveEvents();
		
		return parent::doSetup();
	}
}

/**
 * JAVAEXCEPTION_basic 
 *
 */
final class JAVAEXCEPTION_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that basic JAVAEXCEPTION event generation is working'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'JAVAEXCEPTION.php';
		$this->script_params = array();
		$this->verifier = new V_JAVAEXCEPTION_eventGenerated();
		
		$this->enableActiveEvents('javaexception');
		
		return parent::doSetup();
	}
}

/**
 * JAVAEXCEPTION_disabled 
 *
 */

final class JAVAEXCEPTION_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that when JAVAEXCEPTION event is disabled no event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'JAVAEXCEPTION.php';
		$this->verifier = new V_JAVAEXCEPTION_eventNotGenerated();
		
		$this->enableActiveEvents();
		
		return parent::doSetup();
	}
}



/**
 * HTTPERROR_basic 
 *
 */
final class HTTPERROR_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that basic HTTPERROR event generation is working'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		$this->script_name = 'HTTPERROR.php';
                $this->script_params = array('severe' => 0);
		$this->verifier = new V_HTTPERROR_eventGenerated(0, 404, 'http%3A%2F%2Fwww.not_exist.com');
		$this->enableActiveEvents('httperror');

		return parent::doSetup();
	}
}

/**
 * HTTPERROR_severe 
 *
 */
final class HTTPERROR_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that severe HTTPERROR event generation is working'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		$this->script_name = 'HTTPERROR.php';
                $this->script_params = array('severe' => 1);
		$this->verifier = new V_HTTPERROR_eventGenerated(1, 404, 'http%3A%2F%2Fwww.not_exist.com');
		$this->enableActiveEvents('httperror');

		return parent::doSetup();
	}
}

/**
 * HTTPERROR_disabled 
 *
 */

final class HTTPERROR_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Test that when HTTPERROR event is disabled no event is generated'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		$this->script_name = 'HTTPERROR.php';
		$this->verifier = new V_HTTPERROR_eventNotGenerated();
		$this->enableActiveEvents();

		return parent::doSetup();
	}
}


/**
 * ERROR_REPORTING_userError 
 *
 */

final class ERROR_REPORTING_userError extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR is generated when trigger_error of E_USER_ERROR type is called'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'ERROR_REPORTING.php';
		$this->script_params = array('error_type' => 'E_USER_ERROR');
		$this->verifier = new V_USER_ERROR_eventGenerated();
		
		$this->enableActiveEvents('zenderror');

		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); /*6143*/
		$this->setZendIniDirective("zend_monitor.silence_level", "0");
		
		
		return parent::doSetup();
	}
}

/**
 * ERROR_REPORTING_userWarning 
 *
 */

final class ERROR_REPORTING_userWarning extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR is generated when trigger_error of E_USER_WARNING type is called'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();

		$this->script_name = 'ERROR_REPORTING.php';
		$this->script_params = array('error_type' => 'E_USER_WARNING');

		$this->verifier = new V_USER_WARNING_eventGenerated();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); /*6143*/
		$this->setZendIniDirective("zend_monitor.silence_level", "0");
		
		return parent::doSetup();
	}
}

/**
 * ERROR_REPORTING_userNotice
 *
 */

final class ERROR_REPORTING_userNotice extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('ZENDERROR is generated when trigger_error of E_USER_NOTICE type is called'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'ERROR_REPORTING.php';
		$this->script_params = array('error_type' => 'E_USER_NOTICE');

		$this->verifier = new V_USER_NOTICE_eventGenerated();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); /*6143*/
		$this->setZendIniDirective("zend_monitor.silence_level", "0");
		
		return parent::doSetup();
	}
}

/**
 * SILENCE_LEVEL_reportAll 
 *
 */

final class SILENCE_LEVEL_reportAll extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('A script that contains 2 errors: 1 slienced and the other not. Both should be reported'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SILENCE_LEVEL.php';		
		$this->verifier = new V_SILENCE_LEVEL_reportAll();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.silence_level", "0");
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); 
		$this->setZendIniDirective("zend_monitor.error_level.severe", "0");

		$this->setPhpIniDirective("error_reporting", "0");
		
		return parent::doSetup();
	}
}

/**
 * SILENCE_LEVEL_reportNone
 * 7/5/2007 This TEST will fail because of an existing BUG! 
 * 
 */

final class SILENCE_LEVEL_reportNone extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('A script that contains 2 errors: 1 slienced and the other not. None should be reported. 
one due to silence operator and the other due to php error_reporting directive'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SILENCE_LEVEL.php';		
		$this->verifier = new V_SILENCE_LEVEL_reportNone();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.silence_level", "1"); 
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL");
		$this->setZendIniDirective("zend_monitor.error_level.severe", "0");  

		
		$this->setPhpIniDirective("error_reporting", "0");

	
		return parent::doSetup();
	}
}


/**
 * SILENCE_LEVEL_reportNonSilent1 
 *
 */

final class SILENCE_LEVEL_reportNonSilent1 extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('A script that contains 2 errors: 1 slienced and the other not. Only non-silenced should be reported'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'SILENCE_LEVEL.php';		
		$this->verifier = new V_SILENCE_LEVEL_reportNonSilent();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.silence_level", "1"); 
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); 
		$this->setZendIniDirective("zend_monitor.error_level.severe", "0"); 
		
		$this->setPhpIniDirective("error_reporting", "E_ALL");
		
		return parent::doSetup();
	}
}

/**
 * SILENCE_LEVEL_reportNonSilent2 
 *
 */

final class SILENCE_LEVEL_reportNonSilent2 extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('A script that contains 2 errors: 1 slienced and the other not. Only non-silenced should be reported'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		
		$this->script_name = 'SILENCE_LEVEL.php';		
		$this->verifier = new V_SILENCE_LEVEL_reportNonSilent();
		
		$this->enableActiveEvents('zenderror');
		$this->setZendIniDirective("zend_monitor.silence_level", "2"); 
		$this->setZendIniDirective("zend_monitor.error_level", "E_ALL"); 
		$this->setZendIniDirective("zend_monitor.error_level.severe", "0"); 
		
		$this->setPhpIniDirective("error_reporting", "0");
		
		return parent::doSetup();
	}
}

/**
 * TEMP_STORAGE_basic 
 * This tests the system end-to-end and verifies node collector can survive a disconnection from collector center
 */

final class TEMP_STORAGE_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('Verify that in the absence of the Collector Center, events are stored in Temp Storage in each node. The Events should be delivered when CC is available.'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->script_params = array('sleep'=>'1');

		// expected repeats 0f 1 means 2 events happenned
		$this->verifier = new V_LONGSCRIPT_eventGenerated(900/*ms*/, 0, 2);//Itay: changed from 1 to 2, 2 ocurances of same event should apear in the db. i.e. in the same record repeats=2. sometimes (I heard) it is only 1. If 1 , know this is a bug in the system and not in TE


		//clear the ZendMonitor.TempStorage file 
		if( TEF::isWindows() ){

		}else{
			echo `rm /usr/local/Zend/Platform/logs/ZendMonitor.TempStorage*`;
		}

		// stop Collector Center
		if( TEF::isWindows() ){
			echo `NET STOP "Zend Platform Collector Center"`;
		}else{
			echo `/usr/local/Zend/Platform/bin/collector_center /usr/local/Zend/Platform/etc/ -K `;			
		}

		// give some time for the collector center to stop
		sleep(3);

		TEF::addResourceFileToDocRoot('LONGSCRIPT.php');
		
		$this->enableActiveEvents('longscript');
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "500");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "2000");
		$this->setZendIniDirective("zend_monitor.disable_script_runtime_after_function_runtime", "no");
		$this->setZendIniDirective("zend_monitor.reconnect_timeout","1");
		$this->setZendIniDirective("zend_monitor.debug_mode","1");

		return parent::doSetup();
	}
	
	
	protected function isPassed(){
		$this->logger->funcStart();
		
				
		//start Collector Center
		if( TEF::isWindows() ){
			echo `NET START "Zend Platform Collector Center"`;
		}else{
			echo `/usr/local/Zend/Platform/bin/collector_center /usr/local/Zend/Platform/etc/`;
		}

		// give some time for the collector center to start
		sleep(3);
		
		// trigger another event to cause flushing of temp storage
		TEF::wwwGetLocalhost($this->script_name, $this->script_params );
		
		// give some time for the event to be sent to the collector center
		sleep(3);
		
		$result = $this->verifier->isPassed();
		$test_class_name = get_class($this);
		
		if ($result === true) {
			echo "Success for {$test_class_name} ++++++++++++++++++++++++++++++++++++++++\n";
		} else {
			echo "Failure {$test_class_name} ----------------------------------------\n";
			$this->verifier->dumpAllTables();
		}
		return $result;
	}
}


/**
 * SHAREDMEMORY_basic 
 * This tests that statistics (needed for deviation events) are collected from from several apache processes
 * running in parallel
 */

final class SHAREDMEMORY_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('DEVSCRIPT produced after warmup hits collected from two seperate http servers'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		$this->verifier = new V_DEVSCRIPT_eventGenerated(3000, 1000, 0);
						
		$this->enableActiveEvents('devscript');
		$this->setZendIniDirective("zend_monitor.max_time_dev", "10"); 
		$this->setZendIniDirective("zend_monitor.max_time_dev.severe", "60"); 
		$this->setZendIniDirective("zend_monitor.time_threshold", "0"); 
		$this->setZendIniDirective("zend_monitor.warmup_requests", "2");
		$this->setZendIniDirective("zend_monitor.max_script_runtime", "5000");
		$this->setZendIniDirective("zend_monitor.max_script_runtime.severe", "5000");
		
		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out3.zte http://localhost/LONGSCRIPT.php?sleep=3`;
		
		echo "Waiting for 5 seconds.. untill all async wget are completed\n";
		sleep(5);
	}
}



/**
 * MAXAPACHEPROCESSES_basic 
 *
 */

final class MAXAPACHEPROCESSES_basic extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MAXAPACHEPROCESSES produced when enough apache processes are up'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->script_name = 'LONGSCRIPT.php';
		//TODO set the number of apache processes in httpd.conf
		$this->verifier = new V_MAXAPACHEPROCESSES_eventGenerated(2, 0);
						
		$this->enableActiveEvents('maxapacheprocesses');
		$this->setZendIniDirective("zend_monitor.load_sample_freq", "1");
		$this->setZendIniDirective("zend_monitor.max_apache_processes", "2");
		$this->setZendIniDirective("zend_monitor.max_apache_processes.severe", "20");
		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=1`;

		
		echo "Waiting for 2 seconds.. untill all async wget are completed\n";
		sleep(2);
	}
}


/**
 * MAXAPACHEPROCESSES_severe 
 *
 */
final class MAXAPACHEPROCESSES_severe extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MAXAPACHEPROCESSES produced produced with severe flag when enough apache processes are up'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		

		$this->script_name = 'LONGSCRIPT.php';
		//TODO set the number of apache processes in httpd.conf
		$this->verifier = new V_MAXAPACHEPROCESSES_eventGenerated(4, 1);
						
		$this->enableActiveEvents('maxapacheprocesses');
		$this->setZendIniDirective("zend_monitor.load_sample_freq", "1");
		$this->setZendIniDirective("zend_monitor.max_apache_processes", "2");
		$this->setZendIniDirective("zend_monitor.max_apache_processes.severe", "4");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_4.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_5.zte http://localhost/LONGSCRIPT.php?sleep=1`;

		
		echo "Waiting for 2 seconds.. untill all async wget are completed\n";
		sleep(2);
	}
}


/**
 * MAXAPACHEPROCESSES_disabled 
 *
 */
final class MAXAPACHEPROCESSES_disabled extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MAXAPACHEPROCESSES not produced when event is disabled'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		

		$this->script_name = 'LONGSCRIPT.php';
		//TODO set the number of apache processes in httpd.conf
		$this->verifier = new V_MAXAPACHEPROCESSES_eventNotGenerated();
						
		$this->enableActiveEvents();
		$this->setZendIniDirective("zend_monitor.load_sample_freq", "1");
		$this->setZendIniDirective("zend_monitor.max_apache_processes", "2");
		$this->setZendIniDirective("zend_monitor.max_apache_processes.severe", "4");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_4.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_5.zte http://localhost/LONGSCRIPT.php?sleep=1`;

		
		echo "Waiting for 2 seconds.. untill all async wget are completed\n";
		sleep(2);
	}
}


/**
 * MAXAPACHEPROCESSES_threshhold
 *
 */

final class MAXAPACHEPROCESSES_threshhold extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('MAXAPACHEPROCESSES not produced when max apache processes threshhold is not reached'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		

		$this->script_name = 'LONGSCRIPT.php';
		//TODO set the number of apache processes in httpd.conf
		$this->verifier = new V_MAXAPACHEPROCESSES_eventNotGenerated();
						
		$this->enableActiveEvents('maxapacheprocesses');
		$this->setZendIniDirective("zend_monitor.load_sample_freq", "1");
		$this->setZendIniDirective("zend_monitor.max_apache_processes", "500000");
		$this->setZendIniDirective("zend_monitor.max_apache_processes.severe", "500000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=1`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=1`;

		
		echo "Waiting for 2 seconds.. untill all async wget are completed\n";
		sleep(2);
	}
}


/**
 * MAXAPACHEPROCESSES_counter_decrease
 *
 */
final class MAXAPACHEPROCESSES_counter_decrease extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('verify MAXAPACHEPROCESSES event not produced after apache process count decreases'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		

		$this->script_name = 'LONGSCRIPT.php';
		//TODO set the number of apache processes in httpd.conf
		$this->verifier = new V_MAXAPACHEPROCESSES_eventNotGenerated();
						
		$this->enableActiveEvents('maxapacheprocesses');
		$this->setZendIniDirective("zend_monitor.load_sample_freq", "1");
		$this->setZendIniDirective("zend_monitor.max_apache_processes", "8");
		$this->setZendIniDirective("zend_monitor.max_apache_processes.severe", "50000");

		return parent::doSetup();
	}
	
	public function doSequence() {
		$this->logger->funcStart();
		
		// We are using wget instead of TEF::wwwGetLocalhost in-oreder to produce parallel async requests,
		// which are guarantied to be handled by different apache processes.
		// NOTE: we must use the -O option to direct the output to different files. Or else, the wget 
		//		 will be forced to be syncronized due to file access locking.  
		if( TEF::isWindows() ){
			`del out*.zte`;	
		} else {
			`rm out*.zte`;
		}
		
		// should cause dynamic creation of apache processes
		`wget -b -O out1_1.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_2.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_3.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_4.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_5.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_6.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_7.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_8.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_9.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		`wget -b -O out1_10.zte http://localhost/LONGSCRIPT.php?sleep=2`;
		
		echo "Waiting for 5 seconds.. until all async wget are completed\n";
		sleep(5);

 		echo "Waiting for another 5 seconds.. until dynamically created apache processes are killed\n";
 		sleep(5);

		//clean database
		$this->verifier->removeAllEvents();

 		echo "Waiting for another 3 seconds.. until event will be removed from DB and to give a chance for new event to be generated\n";
 		sleep(3);


	}
}

/**
 * SBL_filteredGET 
 * 
 *
 */

final class SBL_filteredGET extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret GET params are filterd out when event occures'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_filteredGET();
		
		TEF::addResourceFileToDocRoot('SBL.php');

		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "get_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "G");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, array('get_secret' => 'secret'));
		
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_notFilteredGET 
 * 
 *
 */

final class SBL_notFilteredGET extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret GET params are not filterd when event occures becuase of the zend_monitor.security_filtered_variables directive'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_notFilteredGET();
		
		TEF::addResourceFileToDocRoot('SBL.php');
		
		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "get_secret");
		$this->setZendIniDirective("zend_monitor.security_filtered_variables", "");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, array('get_secret' => 'secret'));
		
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}


/**
 * SBL_filteredPOST
 * 
 *
 */

final class SBL_filteredPOST extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret POST+RAW_POST params are filterd when event occures'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_filteredPOST();
		
		TEF::addResourceFileToDocRoot('SBL.php');

		$this->script_name = 'SBL.php';		
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "post_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "P");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwPostLocalhost($this->script_name, array('post_secret' => 'secret'));
		
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_notFilteredPOST
 * 
 *
 */

final class SBL_notFilteredPOST extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret POST+RAW_POST params are not filterd when event occures becuase of the zend_monitor.security_filtered_variables directive'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_notFilteredPOST();
		
		TEF::addResourceFileToDocRoot('SBL.php');

		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "post_secret");
		$this->setZendIniDirective("zend_monitor.security_filtered_variables", "");


		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwPostLocalhost($this->script_name, array('post_secret' => 'secret'));
		
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_filteredCOOKIE
 * 
 *
 */

final class SBL_filteredCOOKIE extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret COOKIE params are filterd when event occures'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_filteredCOOKIE();
		
		TEF::addResourceFileToDocRoot('SBL.php');
		
		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "cookie_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "C");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, array(), array("Cookie: cookie_secret=secret"));
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_filteredCOOKIEOnMultipleCookies
 * 
 *
 */

final class SBL_filteredCOOKIEOnMultipleCookies extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret COOKIE params are filterd when event occures without disturbing other non secret cookies'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_filteredCOOKIEOnMultipleCookies();
		
		TEF::addResourceFileToDocRoot('SBL.php');
		
		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "cookie_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "C");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, array(), array("Cookie: cookie_secret=secret;cookie_no_secret=no_secret"));
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_noFilterCOOKIEWhenGETWasFiltered 
 * 
 *
 */

final class SBL_noFilterCOOKIEWhenGETWasFiltered extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret COOKIE params are filterd when event occures without disturbing other non secret cookies'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_noFilterCOOKIEWhenGETWasFiltered();
		
		TEF::addResourceFileToDocRoot('SBL.php');
		
		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "get_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "G");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, array('get_secret' => 'secret'), array("Cookie: get_secret=secret"));
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

/**
 * SBL_multipeWords 
 * 
 *
 */

final class SBL_multipeWords extends StepBase  {
	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,
		ZteStepDescriptor::createMinDescriptor('secret COOKIE params are filterd when event occures without disturbing other non secret cookies'));
	}
	
	public function doSetup() {
		$this->logger->funcStart();
		
		$this->verifier = new V_SBL_multipleWords;
		
		TEF::addResourceFileToDocRoot('SBL.php');
		
		$this->script_name = 'SBL.php';
		$this->enableActiveEvents('custom');
		$this->setZendIniDirective("zend_monitor.security_black_list", "get_secret1, get_secret2, cookie_secret, cookie_secret, global_secret");
                $this->setZendIniDirective("zend_monitor.security_filtered_variables", "GC");
		
		return parent::doSetup();
	}

	public function doSequence() {
		$this->logger->funcStart();
		
		sleep(1);
		
		$s = TEF::wwwGetLocalhost($this->script_name, 
					array('get_secret1' => 'secret1', 'get_secret2' => 'secret2', 'global_secret' => 'secret'), 
					array("Cookie: cookie_secret=secret;global_secret=secret"));
		// give some time for the event to be passed to the collector center
		sleep(1);
	}
}

////////////////////////////////////////////////////////// Test /////////////////////////////////////

class MonitorEvents extends ZteTestHeadStep {
	public static function getSubStepsClassNames() {
		return array(
//			'LONGSCRIPT_basic',
// 			'LONGSCRIPT_severe',
//			'LONGSCRIPT_disabled',
// 			'DEVSCRIPT_basic', //NOT tested due to bug: 19464
//  		'DEVSCRIPT_severe',
//			'DEVSCRIPT_threshold',
//			'DEVSCRIPT_disabled',
//			'WARMUP_DEVSCRIPT_below',
//			'WARMUP_DEVSCRIPT_above',
//			'LONGFUNCTION_basic',
// 			'LONGFUNCTION_severe',
//			'LONGFUNCTION_disabled',
//			'LONGFUNCTION_notMonitoredFunc',
//			'LONGFUNCTION_enableLONGSCRIPT',
//			'SLOWCONTENTDOWNLOAD_basic',
//			'SLOWCONTENTDOWNLOAD_severe',
//			'SLOWCONTENTDOWNLOAD_disabled',
//			'SLOWCONTENTDOWNLOAD_longfuncOnFpassthru',
//			'SLOWCONTENTDOWNLOAD_max_content_download',
//			'LONGQUERY_basic',
//			'LONGQUERY_withUnderscore',
//			'ZENDERROR_parseerror',
//			'ZENDERROR_parseerror_disabled',
//			'ZENDERROR_noticeerror', 
//			'ZENDERROR_noticeerror_disabled', 
//			'FUNCERROR_basic',
//			'FUNCERROR_disabled',  
//			'FUNCERROR_notMonitored',
//			'DBERROR_basic', 
//			'DBERROR_disabled', 
//			'MEMSIZE_basic',
//			'MEMSIZE_severe',
//			'MEMSIZE_disabled',
//			'DEVMEM_basic',
//			'DEVMEM_severe',
//			'DEVMEM_disabled',
//			'DEVMEM_threshold',
//			'WARMUP_DEVMEM_below',
//			'WARMUP_DEVMEM_above',
////			'OUTPUT_basic', //start from here
//			'OUTPUT_severe',
//			'OUTPUT_disabled',
//			'OUTPUT_threshold',
//			'WARMUP_OUTPUT_below',
//// 			'WARMUP_OUTPUT_above', // TODO: Problem: Known bug
//			'CUSTOM_basic', 
//			'CUSTOM_severe',
//			'CUSTOM_disabled',
//			'ERROR_REPORTING_userError',
//			'ERROR_REPORTING_userWarning',
//			'ERROR_REPORTING_userNotice',
//			'TEMP_STORAGE_basic',
//			'SILENCE_LEVEL_reportAll',
////			'SILENCE_LEVEL_reportNone', // TODO: Problem: 2 events generated
//			'SILENCE_LEVEL_reportNonSilent1',
////			'SILENCE_LEVEL_reportNonSilent2', // TODO: Problem: 2 events sometimes created
//			'SHAREDMEMORY_basic',
//			'MAXAPACHEPROCESSES_basic',
//			'MAXAPACHEPROCESSES_severe',
//			'MAXAPACHEPROCESSES_disabled',
//			'MAXAPACHEPROCESSES_threshhold',
//			'MAXAPACHEPROCESSES_counter_decrease',
//			'SBL_multipeWords',
//			'SBL_filteredGET',
//			'SBL_notFilteredGET',
//			'SBL_filteredPOST',
//			'SBL_notFilteredPOST',
//			'SBL_filteredCOOKIE',
//			'SBL_filteredCOOKIEOnMultipleCookies',
//			'SBL_noFilterCOOKIEWhenGETWasFiltered',
//			'HTTPERROR_basic',
//			'HTTPERROR_severe',
//			'HTTPERROR_disabled',
//			'JAVAEXCEPTION_basic',
//			'JAVAEXCEPTION_disabled',
		);
	}

	public static function createStepDescriptor() {
		return new ZteStepDescriptor(__CLASS__, __FILE__,array(
		'name' => "Zend functional: Monitor",
		'author' => "Dotan & Boaz",
		'desc' => "Testing Monitor functionality"));
	}

	protected function doSetup() {
		$this->logger->funcStart();
		return true;
	}
}

