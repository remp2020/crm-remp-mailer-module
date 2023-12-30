<?php

namespace Crm\RempMailerModule\Hermes;

use Monolog\LogRecord;

class LogRedact
{
    public static function add($filters)
    {
        return function (LogRecord $record) use ($filters) {
            $context = $record->context;
            foreach ($filters as $filter) {
                if (isset($context['payload']['params'][$filter])) {
                    $context['payload']['params'][$filter] = '******';
                }
            }
            return $record->with(context: $context);
        };
    }
}
