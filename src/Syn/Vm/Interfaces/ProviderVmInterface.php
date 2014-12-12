<?php namespace Syn\Vm\Interfaces;

use Syn\Vm\Models\Vm;

interface ProviderVmInterface
{

	/**
	 * Loads Vm information
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function load(Vm $vm);

	/**
	 * Loads all Vm's
	 * @return mixed
	 */
	public static function all();
	/**
	 * Creates a vm
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function create(Vm $vm);

	/**
	 * Starts a vm
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function start(Vm $vm);


	/**
	 * Graceful shutdown
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function halt(Vm $vm);
	/**
	 * Hard stop a vm
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function stop(Vm $vm);

	/**
	 * Hard reboot, power off, on
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function reboot(Vm $vm);

	/**
	 * Soft restart, shut down, boot
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function restart(Vm $vm);

	/**
	 * Stop, end, destroy
	 * @param Vm $vm
	 * @return mixed
	 */
	public static function destroy(Vm $vm);

	/**
	 * Time between calls of Vm's
	 * @return mixed
	 */
	public static function sleep();
}