<?php namespace Syn\Vm\Models;

use Config;
use Syn\Framework\Abstracts\Model;
use Syn\Framework\Exceptions\MissingImplementationException;

class Vm extends Model
{

	public function action($action = null)
	{
		$c = sprintf('Syn\\Vm\\Providers\\%s\\Vm', $this -> provider);
		if(!class_exists($c))
			throw new MissingImplementationException("Provider {$this->provider} not implemented");

		if(is_null($action))
			$action = "load";

		if(!method_exists($c, $action))
			throw new MissingImplementationException("Action {$action} not found for provider {$this->provider}");

		return forward_static_call_array([$c, $action], [&$this]);
	}

	public function getSshPublicKeysAttribute()
	{
		$keys = Config::get('vm::ssh-keys');
		return array_values($keys);
	}



	public function allowCreate()
	{
		return App::make('Visitor') -> admin;
	}

	public function allowEdit()
	{
		return $this -> allowCreate();
	}
}