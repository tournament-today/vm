<?php namespace Syn\Vm\ScheduledCommands;

use Carbon\Carbon;
use Config;
use DB;
use duncan3dc\Helpers\Fork;
use Illuminate\Support\Collection;
use Indatus\Dispatcher\Scheduler;
use Indatus\Dispatcher\Scheduling\Schedulable;
use Indatus\Dispatcher\Scheduling\ScheduledCommand;
use Queue;
use Symfony\Component\Console\Input\InputOption;
use Syn\Notification\Classes\HipChat;
use Syn\Vm\Models\Vm;

class VmScheduledCommand extends ScheduledCommand
{
	protected $name = "vm:schedule";
	protected $description = 'Runs anything related to Vm\'s in the background';


	/**
	 * @Todo make functional again
	 */
	public function fire()
	{
		// for statistics; start
		$start = Carbon::now();

		$this -> info("Starting: {$start}");

		$cup_id = $this->option('cup');
		$query = Vm::query();
		if($cup_id)
			$query->where('cup_id', $cup_id);

		$query_duplicate = clone $query;

		if($this->option('instantiate') || !count($this->getOptions()))
			$vms = $query_duplicate->whereNull('deploying_at') -> get();
		else
			$vms = new Collection();

		$query_duplicate = clone $query;
		if($this->option('destroy') || !count($this->getOptions()))
			// TODO: Use different selection!
			$tearDown = $query_duplicate->whereNull('destroyed_at') -> whereNotNull('vm_id') -> get();
		else
			$tearDown = new Collection();

		$batchSize = Config::get('vm::batch-size');

		// notify of start
		$countUp = $vms -> count();
		$countDown = $tearDown -> count();
		Queue::push(function($job) use ($countUp, $countDown)
		{
			HipChat::messageRoom("Vm batch starting: UP {$countUp}, DOWN {$countDown}", true);
			$job->delete();
		});

		while($vms -> count() > 0)
		{
			$batchStart = Carbon::now();
			$this -> info("Batch #{$batchSize} starting: {$batchStart}");

			if($batchSize)
				$batch = $vms->count() == 1 ? new Collection([$vms->first()]) : new Collection($vms -> random(min($vms->count(), $batchSize)));
			else
				$batch = clone $vms;


			$vms = $vms -> except($batch->lists('id'));

			$this -> runAndWaitForBatch($batch, $batch -> count());

			$batchEnd = Carbon::now();
			$this -> info("Batch #{$batchSize} ended: {$batchEnd} duration: {$batchEnd->diffInSeconds($batchStart)}s");

			Queue::push(function($job) use ($batchSize, $batchEnd, $batchStart)
			{
				HipChat::messageRoom("UP #{$batchSize} ended: {$batchEnd} duration: {$batchEnd->diffInSeconds($batchStart)}s", true);
				$job->delete();
			});
		}

		$end = Carbon::now();

		$this -> info("Run ended: {$end} duration: {$end->diffInSeconds($start)}s");

		if($tearDown->count() > 0)
		{
			$this -> info("Starting immediate tear down");
			while($tearDown -> count() > 0)
			{
				$batchStart = Carbon::now();
				$this -> info("Batch #{$batchSize} starting: {$batchStart}");

				if($batchSize)
					$batch = $tearDown->count() == 1 ? new Collection([$tearDown->first()]) : new Collection($tearDown -> random(min($tearDown->count(), $batchSize)));
				else
					$batch = clone $tearDown;

				$tearDown = $tearDown->except($batch->lists('id'));

				$this -> tearDown($batch, $batch -> count());

				$batchEnd = Carbon::now();
				$this -> info("Batch #{$batchSize} ended: {$batchEnd} duration: {$batchEnd->diffInSeconds($batchStart)}");

				Queue::push(function($job) use ($batchSize, $batchEnd, $batchStart)
				{
					HipChat::messageRoom("DOWN #{$batchSize} ended: {$batchEnd} duration: {$batchEnd->diffInSeconds($batchStart)}s", true);
					$job->delete();
				});
			}
			$tearDownEnd = Carbon::now();
			$this -> info("TearDown ended: {$tearDownEnd} duration: {$tearDownEnd->diffInSeconds($end)}s");
		}

		$endTotal = Carbon::now();
		// notify of start
		Queue::push(function($job) use ($start, $end, $endTotal)
		{
			HipChat::messageRoom("Vm batch ended: UP {$end} ({$end->diffInSeconds($start)}s), DOWN {$endTotal} ({$endTotal->diffInSeconds($end)}s), TOTAL: {$endTotal->diffInSeconds($start)}s", true);
			$job->delete();
		});
	}

	/**
	 * @param     $vms
	 * @param int $count
	 * @return mixed
	 */
	protected function runAndWaitForBatch($vms, $count = 15)
	{
		$fork = new Fork;
		for($i = 1; $i <= $count; $i++)
		{
			if($vms -> count() == 0)
				continue;
			$vm = $vms -> shift();
			$fork -> call(function() use ($vm)
			{
				\DB::reconnect();
				$vm -> action('create');
			});
		}

		$fork -> wait();
		return $vms;
	}

	protected function tearDown($vms, $count = 15)
	{
		$fork = new Fork;
		for($i = 1; $i <= $count; $i++)
		{
			if($vms -> count() == 0)
				continue;
			$vm = $vms -> shift();

			// if sleep, do it now; throttles back end calls
			$vm -> action('sleep');

			$fork -> call(function() use ($vm)
			{
				// reconnects to the database
				DB::reconnect();
				try
				{
					$vm -> action('destroy');
				}
				catch( \Exception $e)
				{
					if($e->getCode() == 404)
						$vm -> delete();
					else
						$this -> error($e -> getMessage());
				}
				finally
				{
					// always close the database connection, even when errors occur
					DB::disconnect();
				}
			});
		}

		$fork -> wait();
		return $vms;
	}

	/**
	 * When a command should run
	 *
	 * @param \Indatus\Dispatcher\Scheduler|\Indatus\Dispatcher\Scheduling\Schedulable $scheduler
	 * @return \Indatus\Dispatcher\Scheduling\Schedulable|\Indatus\Dispatcher\Scheduling\Schedulable[]
	 */
	public function schedule(Schedulable $scheduler)
	{
		return $scheduler -> everyMinutes(1);
	}

	public function environment()
	{
//		return ['production'];
		return ['never'];
	}



	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('instantiate', null, InputOption::VALUE_NONE, 'Only instantiate vms', null),
			array('destroy', null, InputOption::VALUE_NONE, 'Only destroy vms', null),
			array('cup', null, InputOption::VALUE_OPTIONAL, 'Only service vms for set cup', null),
		);
	}
}