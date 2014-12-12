<?php
Route::group(['namespace' => 'Syn\Vm\Controllers'], function()
{
	Route::group([
		'before' 	=> ['auth'],
	], function()
	{

//		Route::any('/steam/sign-in', [
//			'as' => 'Steam@signIn',
//			'uses' => 'SteamOpenIdController@signIn'
//		]);
		Route::any('/vms', [
			'as' => 'Vm@index',
			'uses' => 'VmController@index'
		]);
	});
});