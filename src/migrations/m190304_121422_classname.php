<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce
 */

namespace flipbox\patron\salesforce\migrations;

use craft\db\Migration;
use flipbox\craft\salesforce\records\Connection;
use flipbox\patron\salesforce\connections\PatronConnection;

class m190304_121422_classname extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $records = Connection::find()
            ->andWhere(
                [
                    'class' => "flipbox\\patron\\salesforce\\records\\PatronConnection"
                ]
            )
            ->all();

        $success = true;

        foreach ($records as $record) {
            $record->class = PatronConnection::class;

            // Save
            if (!$record->save(true, ['class'])) {
                $success = false;
            }
        }

        return $success;
    }
}
