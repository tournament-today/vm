<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VmTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('vms', function($t)
		{
			$t -> bigIncrements('id');

			// refer to cup/match
			$t -> bigInteger('match_id') -> unsigned();
			$t -> bigInteger('cup_id') -> unsigned();
			$t -> bigInteger('round_id') -> unsigned();

			// provider specific
			$t -> string('provider') -> nullable();
			$t -> string('vm_id',255) -> nullable();
			$t -> string('ip');

			// requested set up
			$t -> string('hostname');
			$t -> integer('preferred_memory');
			$t -> integer('preferred_cpus');
			$t -> string('preferred_region') -> nullable();
			$t -> integer('preferred_disk') -> nullable();

			// definite cost in cents
			$t -> integer('cost_total') -> nullable();
			$t -> integer('cost_per_minute') -> nullable();

			$t -> timestamp('deploying_at') -> nullable();
			$t -> timestamp('deployed_at') -> nullable();
			$t -> timestamp('destroying_at') -> nullable();
			$t -> timestamp('destroyed_at') -> nullable();

			$t -> timestamps();
			$t -> softDeletes();

			$t -> index('vm_id');
			$t -> index('match_id');
			$t -> index('round_id');
			$t -> index('cup_id');

		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('vms');
	}

}
