<?php
/*
Copyright (c) 2007, Chris Stretton
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
 are met:

    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above
      copyright notice, this list of conditions and the following
      disclaimer in the documentation and/or other materials provided
      with the distribution.

    * Neither the names of The Cheezy Blog, Cheezyblog.net or
      HellaWorld nor the names of its contributors may be used to
      endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
OF THE POSSIBILITY OF SUCH DAMAGE.

$Id: HellaController.php 170 2008-01-12 19:27:57Z drarok $

*/

	require_once 'xmlrpclib/xmlrpc.inc';

	if (!defined('HELLA_DEBUG')) {
		define('HELLA_DEBUG', false);
	}

	Class HellaController {
		/**
		 *	HellaController - An object that sends commands and parses the
		 *	response from HellaNZB via XML-RPC
		 */

		private $client = false;

		// Hella NZB Status Info
		public $version = 0;
		public $uptime = 0;
		public $totalMB = 0;
		public $totalFiles = 0;
		public $totalSegments = 0;
		public $totalNZBs = 0;
		public $paused = false;
		public $transferRate = 0;
		public $eta = 0;
		public $downloadCount = 0;
		public $downloads = array();
		public $processCount = 0;
		public $processing = array();
		public $queueLength = 0;
		public $queueSize = 0;
		public $queue = array();
		public $logLength = 0;
		public $log = array();
		public $multiCall = false;
		public $calls = array();

		public function __construct($host, $port, $username, $password) {

			// Create a new XML RPC client from the PHP XML-RPC
			$this->client = new xmlrpc_client('', $host, $port);

			// Set the username and password for communicating with HellaNZB
			$this->client->setCredentials($username, $password);

			// if debug mode is on, set the XML-RPC client's debug flag
			if (HELLA_DEBUG) {
				$this->client->setDebug(1);
			}

			// Get the current status from HellaNZB
			$this->getStatus();
		}

		public function getStatus() {
		/**
		 *	Gets the current status of HellaNZB via XML-RPC and populates the class variables
		 *	with the information
		 */

			// Send the status command to HellaNZB
			$response = $this->sendCommand('status');

			// Parse the response
			$info = php_xmlrpc_decode($response->value());

			// if the debug flag is set, display the parsed response
			if (HELLA_DEBUG) {
				print_r($info);
			}
			
			// Populate core data from the response into the class member variables
			$this->version = $info['version'];
			$this->uptime = $info['uptime'];
			$this->totalMB = $info['total_dl_mb'];
			$this->totalFiles = $info['total_dl_files'];
			$this->totalSegments = $info['total_dl_segments'];
			$this->totalNZBs = $info['total_dl_nzbs'];
			$this->rateLimit = $info['maxrate'];
			$this->paused = $info['is_paused'];
			$this->completed = $info['percent_complete'];
			$this->remaining = $info['queued_mb'];
			$this->transferRate = $info['rate'];
			$this->eta = $info['eta'];

			// Get the number of items being downloaded
			$this->downloadCount = count($info['currently_downloading']);

			// If theres items being downloaded
			if ($this->downloadCount > 0) {
				$this->downloads = array();

				// Loop through the items and add them to the download info.
				foreach ($info['currently_downloading'] as $download) {
					$download['nzbName'] = str_replace('_', ' ', $download['nzbName']);
					$this->downloads[] = $download;
				}
			}

			// Get the number of items being processed
			$this->processCount = count($info['currently_processing']);

			// if there's items being processed
			if ($this->processCount > 0) { 
				$this->processing = array();

				// Loop through the items and add them to the processing info
				foreach($info['currently_processing'] as $process) {
					$this->processing[] = str_replace('_', ' ', $process['nzbName']);
				}
			}

			// Get the number of items queued
			$this->queueLength = count($info['queued']);
			$this->queueSize = 0;

			// If there are items in the queue
			if ($this->queueLength > 0) {
				$this->queue = array();

				// Loop through each queue item
				foreach($info['queued'] as $queued) {

					// Update the total queue size with the size of this queue item
					// TODO: Add proper handling for when HellaNZB doesnt providea the total_mb
					if (!isset($queued['total_mb'])) {
						$queued['total_mb'] = 0;
					}
					$this->queueSize += $queued['total_mb'];

					// Strip underscores from the NZB name
					$queued['nzbName'] = str_replace('_', ' ', $queued['nzbName']);

					// If the transfer rate is 0
					if ($this->transferRate == 0) {

						// set the queue items ETA to 0
						$queued['eta'] = 0;
					} else {

						// set the queue items ETA to the size of the queue multiplied from MB to KB,
					    // divided by the transfer rate, plus the eta of the currently downloading item.
						$queued['eta'] = round(($this->queueSize * 1024) / $this->transferRate) + $this->eta;
					}

					// populate the queue with this items info.
					$this->queue[] = $queued;
				}
			}

			// Get the number of log entries
			$this->logLength = count($info['log_entries']);

			// if there are log entries
			if ($this->logLength > 0) {
				$this->log = array();

				// loop through them
				foreach ($info['log_entries'] as $log) {

					// Get the information for each log line and populate the log info
					list($type, $line) = each($log);
					$this->log[] = trim($type . ': ' . str_replace(array("\r", "\n"), '', $line));
				}
			}
		}

		private function sendCommand($command, $arguments = '') {
		/**
		 *	Sends a command to HellaNZB via XML-RPC and returns the unparsed response
		 *
		 *	Takes 1 manditory and one optional argument
		 *	$command must be a string containing a command to send via XML-RPC
		 *
		 *	$arguments can either be a string containing a single argument for the XML-RPC command
		 *	or an array of strings, each containing an argument for the XML-RPC command
		 */

			// get the current maximum execution time of the script
			$timeout = ini_get('max_execution_time');

			// if the execution time is available and is greater than 0
			if ($timeout) {

				// if the execution time is greater than 10.
				if ($timeout > 10) {
					
					// set the timeout to the execution time - 5
					$timeout -= 5;
					// or leave the timeout as it is for execution times of 1 to 10 seconds
				}
			} else {

				// if no execution time is available or is unlimited, set the timeout to 25
				$timeout = 25;
			}

			// if the arguments are an array
			if (is_array($arguments)) {

				// initialise a temporary array of arguments to use in the XML-RPC command
				$atmp = array();

				// loop through the arguments
				foreach($arguments as $argument) {

					//add the argument to the argument array as an XML-RPC value
					$type = (is_numeric($arguments) ? 'int' : 'string');
					$atmp[] = new xmlrpcval($argument, $type);
				}

				// set the arguments to the temporary array
				$arguments = $atmp;

			// if the argument is a non empty string
			} elseif ($arguments != '') {

				// convert it to a one item array contianing an XML-RPC value
				$type = (is_numeric($arguments) ? 'int' : 'string');
				$arguments = array(new xmlrpcval($arguments, $type));
			}

			if ($this->multiCall) {
				$this->calls[] = new xmlrpcmsg($command, $arguments);
			} else {
				// create a new xmlrpcmsg object with our command and arguments
				$msg = new xmlrpcmsg($command, $arguments);

				// execute our XML-RPC request with the xmlrpcmsg object and our timeout
				$response = $this->client->send($msg, $timeout);

				// if we recieve a non 0 response code
				if ($response->faultCode() != 0) {
					// throw an exception containing any error message we recieved from the XML-RPC server
					throw new Exception($response->faultString(), $response->faultCode());
				}

				// return the response
				return $response;
			}
		}

		public function multiCallCommit() {
		/**
		 *  Commits a multi-call transaction, sending all commands to the
		 *  XML-RPC server.
		 */
			// Perform some sanity checking before proceeding
			if (!$this->multiCall) {
				throw new Exception('No multicall in progress.');
			}
			if (count($this->calls) == 0) {
				throw new Exception('No calls to execute.');
			}

			// Get the max execution time if possible and set the timeout to be shorter than it.
			$timeout = ini_get('max_execution_time');

			if ($timeout) {
				if ($timeout > 10) {
					$timeout -= 5;
				}
			} else {
				$timeout = 25;
			}

			// Send all the calls to the XML-RPC server.
			$responses = $this->client->multicall($this->calls, $timeout);

			// Handle any issues raised.
			$exceptions = array();
			foreach ($responses as $response) {
				try {
					if ($response->faultCode() != 0) {
						// throw an exception containing any error message we recieved from the XML-RPC server
						throw new Exception($response->faultString(), $response->faultCode());
					}
				} catch (Exception $e) {
					$exceptions[] = $e->getMessage();
				}
			}
			if (count($exceptions) > 0) {
				throw new Exception("XML RPC Errors encountered during multi-call:\n" . implode("\n", $exceptions));
			}
		}

		public function multiCallCancel() {
		/**
		 *  Ends a multi call without committing any changes
		 */
			$this->calls = array();
			$this->multiCall = false;
		}

		public function multiCallStart() {
		/**
		 *	Begins a multiple call transaction
		 */
			$this->calls = array();
			$this->multiCall = true;
		}

		public function cancel() {
		/**
		 *	Cancels the current download and remove it from the queue.
		 */
			$this->sendCommand('cancel');
		}

		public function clear($download = false) {
		/*
		 *	Empties the entire queue.
		 *
		 *	Takes 1 optional argument
		 *	$download is a boolean, if true HellaNZB will also cancel
		 *	the current download as well as emptying the queue.
		 */
			$this->sendCommand('clear', $download);
		}

		public function resume() {
		/**
		 *	Resumes downloading a paused download.
		 */
			$this->sendCommand('continue');
		}

		public function dequeue($nzbid) {
		/**
		 *	Removes the provided NZB id from the queue.
		 *
		 *	Takes one manditory argument
		 *	$nzbid must be an integer value containing the id of the NZB
		 *	to be dequeued.
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('dequeue', $nzbid);
		}

		public function down($nzbid, $shift = 1) {
		/**
		 *	Moves the NZB down in the queue.
		 *
		 *	Takes 1 manditory and 1 optional argument
		 *	$nzbid must be an integer value containing the id of the NZB
		 *	to be moved
		 *
		 *	$shift can be the number of entries it moves down, defaulting
		 *	to 1.
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('down', array($nzbid, $shift));
		}

		public function enqueue($filename) {
		/**
		 *	Enqueues the provided filename in HellaNZB.
		 *	
		 *	Takes 1 manditory argument
		 *	$filename must be a string containing the path to the NZB file
		 */
			if (!file_exists($filename)) throw new Exception('Provided path does not exist');
			$this->sendCommand('enqueue', $filename);
		}

		public function enqueueNewzbin($articleid) {
		/**
		 *	Enqueues NZB files via Newzbin ID.
		 *	
		 *	Takes 1 manditory argument
		 *	$articleid must be an integer value of a Newzbin article.
		 */
			if ($articleid === false) throw new Exception('Invalid ID provided');
			if (!preg_match('/^\d+$/', $articleid)) {
				throw new Exception('Invalid Newzbin article ID provided');
			}
			$this->sendCommand('enqueuenewzbin', $articleid);
		}
		
		public function enqueueURL($url) {
		/**
		 *	Enqueues NZB files provided via a URL.
		 *	
		 *	Takes 1 manditory argument
		 *	$url must be a string containing a url of an NZB file
		 */
			if (preg_match('/((https?):\/\/)?(([A-Z0-9][A-Z0-9_-]*)((\.[A-Z0-9][A-Z0-9_-]*)+)?)(:(\d+))?(\/([^ ]*)?| |$)/i', $url) < 1) {
				throw new Exception('Invalid URL provided');
			}
			$this->sendCommand('enqueueurl', $url);
		}

		public function force($nzbid) {
		/**
		 *	Forces the given NZB id to begin downloading.
		 *	
		 *	Takes 1 manditory argument
		 *	$nzbid must be an integer value containing the id of the NZB
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('force', $nzbid);
		}

		public function last($nzbid) {
		/**
		 *	Moves the given NZB id to the bottom of the queue.
		 *
		 *	Takes 1 manditory argument
		 *	$nzbid must be an integer value containing the id of the NZB
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('last', $nzbid);
		}

		public function listNZBs($excludeids = false) {
		/**
		 *	Retruns a list of NZBs in the queue
		 *
		 *	Takes 1 optional argument
		 *	$excludeids is a boolean value, when true it only returns NZB
		 *	names, when false it returns names and IDs
		 */
			$response = $this->sendCommand('list', $excludeids);
			return php_xmlrpc_decode($response->value());
		}

		public function setRate($rate) {
		/**
		 *	Sets the rate limit for HellaNZB
		 *
		 *	Takes 1 manditory argument
		 *	$rate must be an integer value containing the rate limit.
		 */
			if (!preg_match('/^\d+$/', $rate)) {
				throw new Exception('Invalid rate provided');
			}
			$this->sendCommand('maxrate', $rate);
		}

		public function move($nzbid, $index) {
		/**
		 *	Moves the specified NZB to the specified inded
		 *
		 *	Takes 2 manditory arguments
		 *	$nzbid is the NZB id to move
		 *	$index is the position in the queue to move it to
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('move', array($nzbid, $index));
		}


		public function first($nzbid) {
		/**
		 *	Moves the specified NZB to the top of the queue.
		 *
		 *	Takes 1 manditory argument
		 *	$nzbid is the NZB id to move.
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('next', $nzbid);
		}

		public function pause() {
		/**
		 *	Pauses the current download
		 */
			$this->sendCommand('pause');
		}

		public function shutdown() {
		/**
		 *	Shuts down HellaNZB
		 */
			$this->sendCommand('shutdown');
		}

		public function up($nzbid, $shift = 1) {
		/**
		 *	Moves the specified NZB id up in the queue
		 *
		 *	Takes 1 manditory and 1 optional argument
		 *	$nzbid is the ID of the NZB to move
		 *	$shift is the number of places to move it, defaulting to 1
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('up', array($nzbid, $shift));
		}

		public function setRarPass($nzbid, $pass) {
		/**
		 *	Sets the password to use when processing the given NZB id
		 *
		 *	Takes 2 manditory arguments
		 *	$nzbid is the NZB to set the password for
		 *	$pass is the password to set
		 */
			if ($nzbid === false) throw new Exception('Invalid ID provided');
			$this->sendCommand('setrarpass', array($nzbid, $pass));
		}

		public static function formatTimeStamp($seconds){
		/**
		 *	Formats a given number of seconds into a readable time
		 *
		 *	Takes 1 manditory arugment
		 *	$seconds is the number of seconds to format
		 */
			$days = floor($seconds / 86400);
			$seconds -= ($days * 86400);

			$hours = floor($seconds / 3600);
			$seconds -= ($hours * 3600);

			$minutes = floor($seconds / 60);
			$seconds -= ($minutes * 60);

			$output = '';

			if ($days > 0) {
				$output = $days . 'd ';
			}

			$output .= $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
			return $output;

		}
	}
?>
