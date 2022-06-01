<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use white\commerce\picqer\models\Webhook;
use white\commerce\picqer\records\Webhook as WebhookRecord;

class Webhooks extends Component
{
    /**
     * @param string $type
     * @return Webhook|null
     */
    public function getWebhookByType(string $type): ?Webhook
    {
        $record = WebhookRecord::findOne(['type' => $type]);
        if (!$record) {
            return null;
        }

        return new Webhook($record->toArray());
    }

    /**
     * @param Webhook $model
     * @return bool
     */
    public function saveWebhook(Webhook $model): bool
    {
        if (isset($model->id)) {
            $record = WebhookRecord::findOne([
                'id' => $model->id,
            ]);
        } else {
            $record = new WebhookRecord([
                'type' => $model->type,
            ]);
        }

        $record->picqerHookId = $model->picqerHookId;
        $record->secret = $model->secret;

        $record->save();
        $model->id = $record->getAttribute('id');

        return true;
    }

    /**
     * @param Webhook $webhook
     * @return int
     */
    public function delete(Webhook $webhook): int
    {
        if ($webhook->id) {
            return WebhookRecord::deleteAll(['id' => $webhook->id]);
        }
        
        return 0;
    }
}
