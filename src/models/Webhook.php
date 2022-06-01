<?php


namespace white\commerce\picqer\models;

use craft\base\Model;

class Webhook extends Model
{
    /** @var integer */
    public int $id;

    /** @var string */
    public string $type;

    /** @var integer */
    public int $picqerHookId;

    /** @var string|null */
    public ?string $secret;

    public \DateTime $dateCreated;
    public \DateTime $dateUpdated;
    public string $uid;
    
    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['type', 'picqerHookId'], 'required'],
        ];
    }
}
