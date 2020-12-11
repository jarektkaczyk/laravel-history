<?php

return [
    /**
     * Model of the User performing actions and recorded in the history.
     *
     * @see \Sofa\History\History::user()
     */
    'user_model' => 'App\Models\User',

    /**
     * Custom user resolver for the actions recorded by the package.
     * Should be callable returning a User performing an action, or their raw identifier.
     * By default auth()->id() is used.
     *
     * @see \Sofa\History\HistoryListener::getUserId()
     */
    'user_resolver' => null,

    /**
     * **RETENTION** requires adding cleanup command to your schedule
     *
     * Retention period for the history records.
     * Accepts any parsable date string, eg.
     * '2021-01-01' -> retain anything since 2021-01-01
     * '3 months' -> retain anything no older than 3 months
     * '1 year' -> retain anything no older than 1 year
     * @see strtotime()
     *
     * TODO create the command
     * @see \Sofa\History\RetentionCommand
     */
    'retention' => null,
];
