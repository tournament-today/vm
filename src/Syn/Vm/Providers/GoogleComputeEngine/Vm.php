<?php namespace Syn\Vm\Providers\GoogleComputeEngine;

use App;
use Carbon\Carbon;
use Config;
use Google_Service_Compute_AccessConfig;
use Google_Service_Compute_AttachedDisk;
use Google_Service_Compute_AttachedDiskInitializeParams;
use Google_Service_Compute_Instance;
use Google_Service_Compute_NetworkInterface;
use Google_Service_Compute_Tags;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Syn\Framework\Exceptions\IncorrectExecutionException;
use Syn\Framework\Exceptions\InvalidArgumentException;
use Syn\Framework\Exceptions\UnexpectedResultException;
use Syn\Vm\Interfaces\ProviderVmInterface;


class Vm implements ProviderVmInterface
{

	/**
	 * Source Url's for Vm element definitions
	 */
//	const MACHINE_TYPE = "https://www.googleapis.com/compute/v1/projects/tournament-today/zones/europe-west1-b/machineTypes/f1-micro";
//	const DISK_TYPE = "https://www.googleapis.com/compute/v1/projects/tournament-today/zones/europe-west1-b/diskTypes/pd-standard";
//	const DISK_SOURCE = "https://www.googleapis.com/compute/v1/projects/ubuntu-os-cloud/global/images/ubuntu-1404-trusty-v20141031a";
//	const NETWORKING_TYPE = "https://www.googleapis.com/compute/v1/projects/tournament-today/global/networks/default";
//	const ACCESSCONFIG_TYPE = "ONE_TO_ONE_NAT";

	/**
	 * Vm states
	 */
	const STATE_PROVISIONING = 'PROVISIONING';
	const STATE_STAGING = 'STAGING';
	const STATE_RUNNING = 'RUNNING';
	const STATE_STOPPING = 'STOPPING';
	const STATE_TERMINATED = 'TERMINATED';
	const STATE_PENDING = 'PENDING';

	/**
	 * Time between polling state of Vm
	 */
	const INTERVAL_S = 10;

	/**
	 * Generates complete Url to machine type for respective Zone
	 * @return string
	 */
	private static function getMachineType()
	{
		return sprintf("%sprojects/%s/zones/%s/machineTypes/%s",
			Config::get('vm::google-compute-engine.api-url'),
			Config::get('vm::google-compute-engine.project-id'),
			Config::get('vm::google-compute-engine.zone'),
			Config::get('vm::google-compute-engine.machine-type')
		);
	}

	/**
	 * Generates complete Url to disk type for respective zone
	 * @return string
	 */
	private static function getDiskType()
	{
		return sprintf("%sprojects/%s/zones/%s/diskTypes/%s",
			Config::get('vm::google-compute-engine.api-url'),
			Config::get('vm::google-compute-engine.project-id'),
			Config::get('vm::google-compute-engine.zone'),
			Config::get('vm::google-compute-engine.disk-type')
		);
	}

	/**
	 * Generates complete Url to disk source
	 * @return mixed
	 */
	private static function getDiskSource()
	{
		return Config::get('vm::google-compute-engine.disk-source');
	}

	/**
	 * Generates complete Url to networking type
	 * @return string
	 */
	private static function getNetworkingType()
	{
		return sprintf("%sprojects/%s/global/networks/%s",
			Config::get('vm::google-compute-engine.api-url'),
			Config::get('vm::google-compute-engine.project-id'),
			Config::get('vm::google-compute-engine.networking-type')
		);
	}

	private static function getAccessConfigType()
	{

		return Config::get('vm::google-compute-engine.access-config-type');
	}


	/**
	 * Creates a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @throws \Syn\Framework\Exceptions\IncorrectExecutionException
	 * @throws \Syn\Framework\Exceptions\InvalidArgumentException
	 * @throws \Syn\Framework\Exceptions\UnexpectedResultException
	 * @return mixed
	 * @note should run in CLI, due to sleep
	 */
	public static function create(\Syn\Vm\Models\Vm $vm)
	{
		if(!App::runningInConsole())
			throw new IncorrectExecutionException('Cannot run this command outside of CLI');

		if(!preg_match("/[a-z]([-a-z0-9]*[a-z0-9])?/", $vm -> hostname))
			throw new InvalidArgumentException("Hostname does not match required format");

		// register deployment start time
		$vm -> deploying_at = Carbon::now();
		// prevents other scripts from also trying to deploy
		$vm -> save();
		// run call to api
		$g = Compute::getInstance();
//		\dd($g->instances->list());

		// set up instance
		$instance = new Google_Service_Compute_Instance();
		$instance -> setName("g{$vm->id}");
		$instance -> setMachineType(static::getMachineType());

		// set up disk
		$disk = new Google_Service_Compute_AttachedDisk;
		$disk -> setBoot(true);
		$disk -> setAutoDelete(true);
		$disk -> setType('PERSISTENT');

		// define disk params
		$diskParams = new Google_Service_Compute_AttachedDiskInitializeParams;
		$diskParams -> setSourceImage(static::getDiskSource());
		$diskParams -> setDiskType(static::getDiskType());
		$diskParams -> setDiskSizeGb($vm -> preferred_disk);

		// add disk params to disk
		$disk -> setInitializeParams($diskParams);

		// set disk on instance
		$instance -> setDisks([$disk]);

		// add networking interface
		$networking = new Google_Service_Compute_NetworkInterface;
		$networking -> setNetwork(static::getNetworkingType());

		// access configuration
		$accessConfig = new Google_Service_Compute_AccessConfig;
		$accessConfig -> setType(static::getAccessConfigType());

		// apply access config on networking interface
		$networking -> setAccessConfigs([$accessConfig]);

		// apply networking interface on instance
		$instance -> setNetworkInterfaces([$networking]);

		$googleInstance = $g -> instances -> insert(
			// project Id
			Config::get('vm::google-compute-engine.project-id'),
			// zone
			Config::get('vm::google-compute-engine.zone'),
			// Vm configuration settings
			$instance
		);

		// todo add configured ssh keys

		// sets Vm Id
		$vm -> vm_id = (int) $googleInstance -> id;
		// backup original google Instance
		$originalGoogleInstance = clone $googleInstance;
		// in case of a crash save instance Id to database
		$vm -> save();

		// initial pause
		sleep(60 - static::INTERVAL_S);
		// now loop
		while($googleInstance -> status != static::STATE_RUNNING)
		{
			// wait N seconds
			sleep(static::INTERVAL_S);
			try {
				$googleInstance = $g -> instances -> get(
					// project Id
					Config::get('vm::google-compute-engine.project-id'),
					// zone
					Config::get('vm::google-compute-engine.zone'),
					// Vm Id
					"g{$vm->id}"
				);
			} catch(\Exception $e)
			{
				// attempt to catch that the server is not yet available
				if($e -> getCode() == 404)
					$googleInstance = $originalGoogleInstance;
				else
					throw new UnexpectedResultException("Polling state of VM at Compute Engine failed: {$e->getCode()}: {$e->getMessage()}");
			}
		}

		// deployment succesful
		$vm -> deployed_at = Carbon::now();

		// get after instantiation data; like Ip
		foreach($googleInstance->getNetworkInterfaces() as $networkInterface)
		{
			foreach($networkInterface->getAccessConfigs() as $networkingConfig)
			{
				$vm -> ip = $networkingConfig->getNatIP();
			}
		}

		// store to database
		$vm -> save();

		// create tags
		$tags = new Google_Service_Compute_Tags;
		$tags -> setItems("cup-{$vm->cup_id} round-{$vm->round_id} match-{$vm->match_id} {$vm->hostname}");

		// add tags
/*		$g->instances->setTags(
		// project Id
			Config::get('vm::google-compute-engine.project-id'),
			// zone
			static::$regions["NL"][0],
			// Vm Id
			"g{$vm->id}",
			// tags
			$tags
		);*/
		// todo update Dns settings?
	}

	/**
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return string
	 * @TODO check for vm preferred sizes and return adequate vm size
	 */
	protected static function translateSize(\Syn\Vm\Models\Vm $vm)
	{
		// obsolete for Google Compute Engine
	}

	/**
	 * Starts a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function start(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet() -> powerOn($vm -> vm_id);
	}

	/**
	 * Graceful shutdown
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function halt(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet() -> shutdown($vm -> vm_id);
	}

	/**
	 * Stops a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function stop(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet() -> powerOff($vm -> vm_id);
	}

	/**
	 * Hard reboot, power off, on
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function reboot(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet() -> powerCycle($vm -> vm_id);
	}

	/**
	 * Soft restart, shut down, boot
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function restart(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet() -> reboot($vm -> vm_id);
	}

	/**
	 * Stop, end, destroy
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function destroy(\Syn\Vm\Models\Vm $vm)
	{
		$vm -> destroying_at = Carbon::now();
		$g = Compute::getInstance();
		$g -> instances -> delete(
			// project Id
			Config::get('vm::google-compute-engine.project-id'),
			// zone
			Config::get('vm::google-compute-engine.zone'),
			// Vm Id
			"g{$vm->id}"
		);
		$vm -> destroyed_at = Carbon::now();
		return $vm -> save();
	}

	/**
	 * Loads Vm information
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function load(\Syn\Vm\Models\Vm $vm)
	{
//		return DigitalOcean::droplet()->getById($vm -> vm_id);
	}

	/**
	 * destroy all machines on the remote, in case of emergency stop
	 */
	public static function destroyAllHard()
	{
//		$vms = DigitalOcean::droplet()->getAll();
//		foreach($vms as $vm)
//			DigitalOcean::droplet()->delete($vm->id);
	}

	/**
	 * Loads all Vm's
	 *
	 * @return mixed
	 */
	public static function all()
	{
		$g = Compute::getInstance();
		return $g->instances->listInstances();
	}

	/**
	 * Time between calls of Vm's
	 *
	 * @return mixed
	 */
	public static function sleep()
	{
		return sleep(1);
	}

	/**
	 * Average time to create & boot a Vm
	 *
	 * @info actually 1.7 with a test of 300+
	 * @return int seconds
	 */
	public static function createDuration()
	{
		return 2;
	}

	/**
	 * Average time to stop a Vm
	 *
	 * @return int seconds
	 */
	public static function destroyDuration()
	{
		return 1;
	}
}