<?php namespace Maclof\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\ServiceProvider;

class Revisionable extends Eloquent
{
    /**
     * An array of all of the original data.
     *
     * @var array
     */
    protected $originalData;

    /**
     * An array of all of the updated data.
     *
     * @var array
     */
    protected $updatedData;

    /**
     * Whether or not the current model operation is an update.
     *
     * @var bool
     */
    protected $isUpdating;

    /**
     * Whether or not we should keep revisions of the model.
     *
     * @var bool
     */
    protected $revisionEnabled = true;

    /**
     * A list of fields that should have revisions kept for the model.
     *
     * @var array
     */
    protected $keepRevisionOf = array();

    /**
     * A list of fields that should be ignored when keeping revisions of the model.
     *
     * @var array
     */
    protected $dontKeepRevisionOf = array();


    /**
     * Keeps the list of values that have been updated
     *
     * @var array
     */
    protected $dirty = array();

    /**
     * Returns a collection of the revision history.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function revisionHistory()
    {
        return $this->morphMany('\Maclof\Revisionable\Revision', 'revisionable');
    }

    /**
     * Create the event listeners for the saving and saved events.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = array())
    {
        $this->beforeSave();

        if(parent::save($options))
        {
            $this->afterSave();

            return true;
        }

        return false;
    }

    /**
     * Called before a model is saved.
     *
     * @return void
     */
    public function beforeSave()
    {
        if($this->revisionEnabled)
        {
            $this->originalData = $this->original;
            $this->updatedData = $this->attributes;

            // we can only safely compare basic items,
            // so for now we drop any object based items, like DateTime
            foreach($this->updatedData as $key => $val)
            {
                if(is_object($val))
                {
                    unset($this->originalData[$key]);
                    unset($this->updatedData[$key]);
                }
            }

            $this->dirty = $this->getDirty();
            $this->isUpdating = $this->exists;
        }
    }


    /**
     * Called after a model is successfully saved.
     *
     * @return void
     */
    public function afterSave()
    {
        // Check if revisions are enabled and we're performing an update
        if($this->revisionEnabled AND $this->isUpdating)
        {
            $changes = $this->changedRevisionableFields();

            foreach($changes as $key => $change)
            {
                $revision                    = new Revision();
                $revision->revisionable_type = get_class($this);
                $revision->revisionable_id   = $this->getKey();
                $revision->key               = $key;
                $revision->old_value         = (isset($this->originalData[$key]) ? $this->originalData[$key]: null);
                $revision->new_value         = $this->updatedData[$key];
                $revision->user_id           = (\Auth::user() ? \Auth::user()->id : null);
                $revision->save();
            }
        }
    }


    /**
     * Get an array of all of the fields which we keep track of and have been changed.
     *
     * @return array fields with new data, that should be recorded
     */
    protected function changedRevisionableFields()
    {
        $fields = array();

        foreach($this->dirty as $key => $value)
        {
            if($this->isRevisionable($key))
            {
                $fields[$key] = $value;
            }
            else
            {
                // we don't need these any more, and they could
                // contain a lot of data, so lets trash them.
                unset($this->updatedData[$key]);
                unset($this->originalData[$key]);
            }
        }

        return $fields;
    }

    /**
     * Check if this field should have a revision kept for it.
     *
     * @param  string $key
     * @return bool
     */
    protected function isRevisionable($key)
    {
        // If the field is explicitly revisionable, then return true.
        // If it's explicitly not revisionable, return false.
        // Otherwise, if neither condition is met, only return true if
        // we aren't specifying revisionable fields.
        if (in_array($key, $this->keepRevisionOf)) return true;
        if (in_array($key, $this->dontKeepRevisionOf)) return false;

        return empty($this->keepRevisionOf);
    }

    /**
     * Identifiable Name
     * When displaying revision history, when a foreigh key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     *
     * @return string an identifying name for the model
     */
    public function identifiableName()
    {
        return $this->id;
    }

    /**
     * Disable a single field or an array of fields from being kept revisions of.
     * 
     * @param mixed $field
     * @return void
     */
    public function disableRevisionField($field)
    {
        if(is_array($field))
        {
            $this->dontKeepRevisionOf = array_merge($field, $this->dontKeepRevisionOf);
        }
        else
        {
            $this->dontKeepRevisionOf[] = $field;
        }
    }
}