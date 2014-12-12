<?php namespace Syn\Vm\Providers\DigitalOcean;

use App;
use Carbon\Carbon;
use Config;
use Syn\Framework\Exceptions\IncorrectExecutionException;
use Syn\Vm\Interfaces\ProviderVmInterface;
use GrahamCampbell\DigitalOcean\Facades\DigitalOcean;

class Vm implements ProviderVmInterface
{
	protected static $regions = [
		"NL" => ['ams2', 'ams3']
	];


	const STATE_NEW = 'new';
	const STATE_ACTIVE = 'active';
	const STATE_OFF = 'off';
	const STATE_ARCHIVE = 'archive';

	/**
	 * Creates a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @throws \Syn\Framework\Exceptions\IncorrectExecutionException
	 * @return mixed
	 * @note should run in CLI, due to sleep
	 */
	public static function create(\Syn\Vm\Models\Vm $vm)
	{
		if(!App::runningInConsole())
			throw new IncorrectExecutionException('Cannot run this command outside of CLI');
		// register deployment start time
		$vm -> deploying_at = Carbon::now();
		// prevents other scripts from also trying to deploy
		$vm -> save();
		// run call to api
		$droplet = DigitalOcean::droplet() -> create(
			// hostname
			$vm -> hostname,
			// region
			// TODO check availability in region
			static::$regions['NL'][0],
			// size
			static::translateSize($vm),
			// image
			"ubuntu-14-04-x64",
			// backups
			false,
			// ipv6
			false,
			// private networking
			false,
			// ssh keys for root
			$vm -> sshPublicKeys
		);


		// sets Vm id
		$vm -> vm_id = $droplet -> id;
		// in case of a crash save Id to db
		$vm -> save();

		// sets current state
		$state = $droplet -> status;

		while($state != static::STATE_ACTIVE)
		{
			// wait 30 seconds
			sleep(30);
			$test = static::load($vm);
			$state = $test -> status;
		}

		// register deployment finish time
		$vm -> deployed_at = Carbon::now();

		// replace digital ocean object with test, only happens if vm was done building immediately (never happens)
		if(isset($test))
			$droplet = $test;

		// update networking ip
		foreach($droplet -> networks as $network)
		{
			if($network -> type == "public")
			{
				$vm -> ip = $network -> ipAddress;
				break;
			}
		}
		// save
		$vm -> save();

		// now update dns record
		//public function create($domainName, $type, $name, $data, $priority = null, $port = null, $weight = null)
		DigitalOcean::domainRecord() -> create(
			Config::get('vm::hostname'),
			'A',
			$vm -> hostname,
			$vm -> ip
		);
	}

	/**
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return string
	 * @TODO check for vm preferred sizes and return adequate vm size
	 */
	protected static function translateSize(\Syn\Vm\Models\Vm $vm)
	{
		return "512mb";
	}

	/**
	 * Starts a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function start(\Syn\Vm\Models\Vm $vm)
	{
		return DigitalOcean::droplet() -> powerOn($vm -> vm_id);
	}

	/**
	 * Graceful shutdown
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function halt(\Syn\Vm\Models\Vm $vm)
	{
		return DigitalOcean::droplet() -> shutdown($vm -> vm_id);
	}

	/**
	 * Stops a vm
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function stop(\Syn\Vm\Models\Vm $vm)
	{
		return DigitalOcean::droplet() -> powerOff($vm -> vm_id);
	}

	/**
	 * Hard reboot, power off, on
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function reboot(\Syn\Vm\Models\Vm $vm)
	{
		return DigitalOcean::droplet() -> powerCycle($vm -> vm_id);
	}

	/**
	 * Soft restart, shut down, boot
	 *
	 * @param \Syn\Vm\Models\Vm $vm
	 * @return mixed
	 */
	public static function restart(\Syn\Vm\Models\Vm $vm)
	{
		return DigitalOcean::droplet() -> reboot($vm -> vm_id);
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
		DigitalOcean::droplet() -> delete($vm -> vm_id);
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
		return DigitalOcean::droplet()->getById($vm -> vm_id);
	}

	/**
	 * destroy all machines on the remote, in case of emergency stop
	 */
	public static function destroyAllHard()
	{
		$vms = DigitalOcean::droplet()->getAll();
		foreach($vms as $vm)
			DigitalOcean::droplet()->delete($vm->id);
	}

	/**
	 * Loads all Vm's
	 *
	 * @return mixed
	 */
	public static function all()
	{
		// TODO: Implement all() method.
	}

	/**
	 * Time between calls of Vm's
	 *
	 * @return mixed
	 */
	public static function sleep()
	{
		// TODO: Implement sleep() method.
	}
}