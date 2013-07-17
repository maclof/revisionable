<?php namespace Maclof\Revisionable;

/**
 * Revision
 *
 * Base model to allow for revision history on
 * any model that extends this model
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 */

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\Config;
use Maclof\Revisionable\Exceptions\ModelNotFoundException;

class Revision extends Eloquent
{
	public $table = 'revisions';

	protected $revisionFormattedFields = array();

	protected $revisionNullString = 'nothing';

	protected $revisionUnknownString = 'unknown';

	public function __construct(array $attributes = array())
	{
		parent::__construct($attributes);
	}

	/**
	 * Revisionable
	 * Grab the revision history for the model that is calling
	 *
	 * @return array revision history
	 */
	public function revisionable()
	{
		return $this->morphTo();
	}

	/**
	 * Field Name
	 * Returns the field that was updated, in the case that it's a foreighn key
	 * denoted by a suffic of "_id", then "_id" is simply stripped
	 *
	 * @return string field
	 */
	public function fieldName()
	{
		if(strpos($this->key, '_id'))
		{
			return str_replace('_id', '', $this->key);
		}

		return $this->key;
	}

	/**
	 * Old Value
	 * Grab the old value of the field, if it was a foreign key
	 * attempt to get an identifying name for the model
	 *
	 * @return string old value
	 */
	public function oldValue()
	{
		if(is_null($this->old_value) or strlen($this->old_value) == 0)
		{
			return $this->revisionNullString;
		}

		try
		{
			if(strpos($this->key, '_id'))
			{
				$model = str_replace('_id', '', $this->key);
				$item  = $model::find($this->old_value);

				if(!$item)
				{
					return $this->format($this->key, $this->revisionUnknownString);
				}

				return $this->format($this->key, $item->identifiableName());
			}
		}
		catch (Exception $e)
		{
			// Just a failsafe, in the case the data setup isn't as expected.
		}

		return $this->format($this->key, $this->old_value);
	}


	/**
	 * New Value
	 * Grab the new value of the field, if it was a foreign key
	 * attempt to get an identifying name for the model
	 *
	 * @return string old value
	 */
	public function newValue()
	{
		if(is_null($this->new_value) or strlen($this->new_value) == '')
		{
			return $this->revisionNullString;
		}

		try
		{
			if(strpos($this->key, '_id'))
			{
				$model = str_replace('_id', '', $this->key);
				$item  = $model::find($this->new_value);

				if(!$item)
				{
					return $this->format($this->key, $this->revisionUnknownString);
				}

				return $this->format($this->key, $item->identifiableName());
			}
		}
		catch(Exception $e)
		{
			// Just a failsafe, in the case the data setup isn't as expected.
		}

		return $this->format($this->key, $this->new_value);
	}


	/**
	 * User Responsible
	 *
	 * @return mixed Either an instance of the user model if found, otherwise null.
	 */
	public function userResponsible()
	{
		return $this->getUserModel()->find($this->user_id);
	}

	/**
	 * Attempt to create and return a new instance of the user model.
	 *
	 * @return mixed
	 */
	protected function getUserModel()
	{
		$userModel = Config::get('maclof/revisionable::user_model', 'User');

		if(!class_exists($userModel))
		{
			throw new ModelNotFoundException('The model ' . $userModel . ' was not found.');
		}

		return new $userModel;
	}

	/**
	 * Format the value according to the $revisionFormattedFields array
	 *
	 * @param  $key
	 * @param  $value
	 *
	 * @return string formated value
	 */
	public function format($key, $value)
	{
		$model = $this->revisionable_type;
		$model = new $model;
		$revisionFormattedFields = $model->getRevisionFormattedFields();

		if(isset($revisionFormattedFields[$key]))
		{
			return FieldFormatter::format($key, $value, $revisionFormattedFields);
		}

		return $value;
	}
}