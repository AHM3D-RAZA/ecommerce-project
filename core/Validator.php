<?php

class Validator
{
    private $errors = [];

    public function required($value, $field)
    {
        if (trim((string) $value) === '') {
            $this->errors[$field] = ucfirst($field) . ' is required.';
        }
        return $this;
    }

    public function email($value, $field = 'email')
    {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function minLength($value, $length, $field)
    {
        if (strlen((string) $value) < $length) {
            $this->errors[$field] = ucfirst($field) . " must be at least {$length} characters.";
        }
        return $this;
    }

    public function matches($value, $other, $field)
    {
        if ($value !== $other) {
            $this->errors[$field] = 'Passwords do not match.';
        }
        return $this;
    }

    public function numeric($value, $field)
    {
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = ucfirst($field) . ' must be a number.';
        }
        return $this;
    }

    public function fails()
    {
        return count($this->errors) > 0;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function first()
    {
        return reset($this->errors) ?: null;
    }
}
