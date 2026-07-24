<?php

/*
 *
* ____       ____
 * |  _ \ ___ / ___|___  _ __ ___
 * | |_) / _ \ |   / _ \| '__/ _ \
 * |  _ <  __/ |__| (_) | | |  __/
 * |_| \_\___|\____\___/|_|  \___|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author ReCoreTeam
 * @link https://github.com/AerQAQ/ReCore
 *
 *
*/

namespace pocketmine\utils;

use LogLevel;
use pocketmine\Thread;
use pocketmine\Worker;

class MainLogger extends \AttachableThreadedLogger{
	protected $logFile;
	protected $logStream;
	protected $shutdown;
	protected $logDebug;
	private $logResource;
	/** @var MainLogger */
	public static $logger = null;
	
	private $consoleCallback;

	/** Extra Settings */
	protected $write = true;

	public $shouldSendMsg = "";
	public $shouldRecordMsg = false;
	private $lastGet = 0;

	public function setSendMsg($b){
		$this->shouldRecordMsg = $b;
		$this->lastGet = time();
	}

	public function getMessages(){
		$msg = $this->shouldSendMsg;
		$this->shouldSendMsg = "";
		$this->lastGet = time();
		return $msg;
	}

	/**
	 * @param string $logFile
	 * @param bool   $logDebug
	 *
	 * @throws \RuntimeException
	 */
	public function __construct($logFile, $logDebug = false){
		if(static::$logger instanceof MainLogger){
			throw new \RuntimeException("MainLogger has been already created");
		}
		static::$logger = $this;
		@touch($logFile);  // 修复：添加 @ 抑制 Android 权限警告
		$this->logFile = $logFile;
		$this->logDebug = (bool) $logDebug;
		$this->logStream = new \Threaded;
		$this->start();
	}

	/**
	 * @return MainLogger
	 */
	public static function getLogger(){
		return static::$logger;
	}
	
	//EMERGENCY 紧急 Level 7
	public function emergency($message, $name = "EMERGENCY"){
		$this->send($message, \LogLevel::EMERGENCY, $name, TextFormat::RED);
	}
	//ALERT 警报 Level 6
	public function alert($message, $name = "ALERT"){
		$this->send($message, \LogLevel::ALERT, $name, TextFormat::RED);
	}
	//CRITICAL 严重 Level 5
	public function critical($message, $name = "CRITICAL"){
		$this->send($message, \LogLevel::CRITICAL, $name, TextFormat::RED);
	}
	//ERROR 错误 Level 4
	public function error($message, $name = "ERROR"){
		$this->send($message, \LogLevel::ERROR, $name, TextFormat::DARK_RED);
	}
	//WARNING 警告 Level 3
	public function warning($message, $name = "WARNING"){
		$this->send($message, \LogLevel::WARNING, $name, TextFormat::YELLOW);
	}
	//NOTICE 通知 Level 2
	public function notice($message, $name = "NOTICE"){
		$this->send($message, \LogLevel::NOTICE, $name, TextFormat::AQUA);
	}
	//INFO 信息 Level 1
	public function info($message, $name = "INFO"){
		$this->send($message, \LogLevel::INFO, $name, TextFormat::WHITE);
	}
	//DEBUG 调试 Level 0
	public function debug($message, $name = "DEBUG"){
		if($this->logDebug === false){
			return;
		}
		$this->send($message, \LogLevel::DEBUG, $name, TextFormat::GRAY);
	}

	/**
	 * @param bool $logDebug
	 */
	public function setLogDebug($logDebug){
		$this->logDebug = (bool) $logDebug;
	}

	public function logException(\Throwable $e, $trace = null){
		if($trace === null){
			$trace = $e->getTrace();
		}
		$errstr = $e->getMessage();
		$errfile = $e->getFile();
		$errno = $e->getCode();
		$errline = $e->getLine();

		$errorConversion = [
			0 => "EXCEPTION",
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED",
		];
		if($errno === 0){
			$type = LogLevel::CRITICAL;
		}else{
			$type = ($errno === E_ERROR or $errno === E_USER_ERROR) ? LogLevel::ERROR : (($errno === E_USER_WARNING or $errno === E_WARNING) ? LogLevel::WARNING : LogLevel::NOTICE);
		}
		$errno = isset($errorConversion[$errno]) ? $errorConversion[$errno] : $errno;
		if(($pos = strpos($errstr, "\n")) !== false){
			$errstr = substr($errstr, 0, $pos);
		}
		$errfile = \pocketmine\cleanPath($errfile);
		$this->log($type, get_class($e) . ": \"$errstr\" ($errno) in \"$errfile\" at line $errline");
		foreach(@\pocketmine\getTrace(1, $trace) as $i => $line){
			$this->debug($line);
		}
	}

	public function log($level, $message){
		switch($level){
			case LogLevel::EMERGENCY:
				$this->emergency($message);
				break;
			case LogLevel::ALERT:
				$this->alert($message);
				break;
			case LogLevel::CRITICAL:
				$this->critical($message);
				break;
			case LogLevel::ERROR:
				$this->error($message);
				break;
			case LogLevel::WARNING:
				$this->warning($message);
				break;
			case LogLevel::NOTICE:
				$this->notice($message);
				break;
			case LogLevel::INFO:
				$this->info($message);
				break;
			case LogLevel::DEBUG:
				$this->debug($message);
				break;
		}
	}

	public function shutdown(){
		$this->shutdown = true;
	}

	protected function send($message, $level, $prefix, $color){
		$now = time();

		$thread = \Thread::getCurrentThread();
		if($thread === null){
			$threadName = "Server thread";
		}elseif($thread instanceof Thread or $thread instanceof Worker){
			$threadName = $thread->getThreadName() . " thread";
		}else{
			$threadName = (new \ReflectionClass($thread))->getShortName() . " thread";
		}

		if($this->shouldRecordMsg){
			if((time() - $this->lastGet) >= 10) $this->shouldRecordMsg = false; // 10 secs timeout
			else{
				if(strlen($this->shouldSendMsg) >= 10000) $this->shouldSendMsg = "";
				$this->shouldSendMsg .= $color . "|" . $prefix . "|" . trim($message, "\r\n") . "\n";
			}
		}
		
		// 获取带毫秒的时间
		$microtime = microtime(true);
		$seconds = floor($microtime);
		$milliseconds = round(($microtime - $seconds) * 1000);
		
		$dateTime = new \DateTime();
		$dateTime->setTimestamp($seconds);
		$timeString = $dateTime->format("H:i:s") . TextFormat::DARK_GREEN . '.' . sprintf('%03d', $milliseconds) . TextFormat::GREEN;
		
		$message = TextFormat::toANSI(TextFormat::GREEN . $timeString . TextFormat::RESET . " " . $color . $threadName . "/" . $prefix . " §8> " . $color . $message . TextFormat::RESET);
		$cleanMessage = TextFormat::clean($message);

		if(!Terminal::hasFormattingCodes()){
			echo $cleanMessage . PHP_EOL;
		}else{
			echo $message . PHP_EOL;
		}

		if(isset($this->consoleCallback)){
			call_user_func($this->consoleCallback);
		}

		if($this->attachment instanceof \ThreadedLoggerAttachment){
			$this->attachment->call($level, $message);
		}

		$this->logStream[] = date("Y-m-d", $now) . " " . $cleanMessage . "\n";
		if($this->logStream->count() === 1){
			$this->synchronized(function(){
				$this->notify();
			});
		}
	}

	public function run(){
		$this->shutdown = false;
		if($this->write){
			while($this->shutdown === false){
				if(!$this->write) break;
				$this->synchronized(function(){
					while($this->logStream->count() > 0){
						$chunk = $this->logStream->shift();
						$this->logResource = file_put_contents($this->logFile, $chunk, FILE_APPEND);
					}

					$this->wait(200000);
				});
			}

			if($this->logStream->count() > 0){
				while($this->logStream->count() > 0){
					$chunk = $this->logStream->shift();
					$this->logResource = file_put_contents($this->logFile, $chunk, FILE_APPEND);
				}
			}
		}
	}

	public function setWrite($write){
		$this->write = $write;
	}
	
	public function setConsoleCallback($callback){
		$this->consoleCallback = $callback;
	}
	
	public function directSend($message){
		if(Terminal::hasFormattingCodes()){
			echo TextFormat::toANSI($message) . PHP_EOL;
		}else{
			echo TextFormat::clean($message) . PHP_EOL;
		}
	}
}