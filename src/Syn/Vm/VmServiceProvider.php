<?php namespace Syn\Vm;

use Illuminate\Support\ServiceProvider;

class VmServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	public function boot()
	{
		$this -> package('syn/vm');

		$this -> commands('vm.schedule');
	}
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this -> app -> bind('vm.schedule', function()
		{
			return new ScheduledCommands\VmScheduledCommand();
		});


		include __DIR__ . '/../../routes.php';
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
