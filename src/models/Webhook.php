<?php


namespace white\commerce\picqer\models;


use craft\base\Model;

class Webhook extends Model
{
    /** @var integer */
    public $id;

    /** @var string */
    public $type;

    /** @var integer */
    public $picqerHookId;

    /** @var string|null */
    public $secret;

    public $dateCreated;
    public $dateUpdated;
    public $uid;
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'picqerHookId'], 'required'],
        ];
    }
}
