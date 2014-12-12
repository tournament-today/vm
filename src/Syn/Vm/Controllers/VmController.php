<?php namespace Syn\Vm\Controllers;

use Syn\Framework\Abstracts\Controller;
use Syn\Vm\Models\Vm;

class VmController extends Controller
{

	public function __construct(Vm $model)
	{
		$this -> model = $model;
	}

	public function index()
	{
		if(!$this->model->allowView())
			return $this -> notAllowed('view vm', 'missing access rights');
		$items = $this -> model -> paginate(50);
		$this -> title = trans_choice('vm.vm',2);
		return $this -> view('pages.vm.index', compact('items'));
	}
}