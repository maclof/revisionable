<?php namespace Maclof\Revisionable;

use Illuminate\Support\ServiceProvider;

class RevisionableServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('maclof/revisionable', 'maclof/revisionable');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}
}