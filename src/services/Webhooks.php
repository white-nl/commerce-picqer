<?php


namespace white\commerce\picqer\services;


use craft\base\Component;
use white\commerce\picqer\models\Webhook;
use white\commerce\picqer\records\Webhook as WebhookRecord;

class Webhooks extends Component
{
    public function getWebhookByType($type)
    {
        $record = WebhookRecord::findOne(['type' => $type]);
        if (!$record) {
            return null;
        }

        return new Webhook($record);
    }
    
    public function saveWebhook(Webhook $model)
    {
        $record = WebhookRecord::findOne([
            'id' => $model->id,
        ]);
        if (!$record) {
            $record = new WebhookRecord([
                'type' => $model->type,
            ]);
        }

        $record->picqerHookId = $model->picqerHookId;
        $record->secret = $model->secret;

        $record->save();
        $model->id = $record->id;

        return true;
    }

    public function delete(Webhook $webhook)
    {
        if ($webhook->id) {
            return WebhookRecord::deleteAll(['id' => $webhook->id]);
        }
        
        return 0;
    }
}
