<?php

declare(strict_types=1);

namespace Sitesketch;

class FormBuilder extends ContentBuilder
{
    protected static $debugging = false;

    protected $require_processing_for_render = true;
    protected $should_build_fields = true;
    protected $data;
    protected $processed = false;
    protected $fields_built = false;
    protected $fields_content;
    protected $validates;
    protected $validation_attempted = false;
    protected $redirects;

    public $messages;


    public function __construct($template = false, $redirects = false)
    {
        if ($template) {
            $this->template = $template;
        }
        if ($redirects) {
            $this->redirects = $redirects;
        }
    }

    public function process($data = false)
    {
        if ($data && is_array($data)) {
            $this->data = $data;
        } elseif (!isset($this->data) || !is_array($this->data)) {
            $this->data = [];
        }

        // If validation not attempted, validate
        if (!$this->validation_attempted) {
            if (isset($_REQUEST) && count($_REQUEST)) {
                $this->data = array_merge($this->data, $_REQUEST);
            }
        }

        $this->processed = true;
    }

    public function hasProcessed()
    {
        return $this->processed;
    }

    protected function redirect($redirects = false)
    {
        if (!$redirects) {
            if (isset($this->redirects) && count($this->redirects)) {
                $redirects = $this->redirects;
            } else {
                return false;
            }
        }

        if ($this->validation_attempted) {
            if ($this->validates && isset($redirects['validates'])) {
                header('Location: ' . $redirects['validates']);
                exit();
            }
        }
    }

    public function getData()
    {
        if ((!isset($this->data) || !$this->data || !count($this->data)) && !$this->processed) {
            $this->process();
        }
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function addData($additional_data)
    {
        $this->data = array_merge($this->data, $additional_data);
    }

    protected function buildFields($fields = false)
    {
        if (!$fields) {
            if (isset($this->fields)) {
                $fields = $this->fields;
            } else {
                return false;
            }
        }

        $this->fields_built = true;
    }

    public function setShouldBuildFields($should_build_fields)
    {
        $this->should_build_fields = $should_build_fields;
    }

    public function getContent($template = false, $additional_replacements = false)
    {

        if (!$template) {
            $template = $this->template;
        }

        if ($this->require_processing_for_render && !$this->processed) {
            $this->process();
        }

        if ($this->should_build_fields && !$this->fields_built) {
            $this->buildFields();
        }

        $replacements = $this->getData();
        if (is_array($additional_replacements)) {
            $replacements = array_merge($replacements, $additional_replacements);
        }

        $content = parent::getContent($template, $replacements);

        return $content;
    }
}
