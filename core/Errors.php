<?php

/**
 * Renders validation failures as ONE boxed list above a form, instead of a red
 * line under each field (which reads like the browser's own HTML5 messages).
 *
 * Accepts whatever the form collected:
 *   - Validator::errors()  => ['field' => 'message', ...]
 *   - a plain list          => ['message', 'message', ...]
 *   - a single message      => 'message'
 * Duplicate messages are collapsed so the list stays short and readable.
 */
function render_error_summary($errors)
{
    $messages = [];

    foreach ((array) $errors as $value) {
        // A field can hold either one message or a list of them (rejected uploads).
        foreach ((array) $value as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                $messages[] = $message;
            }
        }
    }

    $messages = array_values(array_unique($messages));

    if ($messages === []) {
        return;
    }

    echo '<div class="alert alert-danger mb-3" role="alert">';
    echo '<p class="mb-2 fw-bold">Please fix the following before saving:</p>';
    echo '<ul class="mb-0 ps-3">';

    foreach ($messages as $message) {
        echo '<li>' . htmlspecialchars($message) . '</li>';
    }

    echo '</ul></div>';
}