<?php

declare(strict_types=1);

namespace Sitesketch;

class FormBuilder extends ContentBuilder
{
    protected static $debugging = false;

    protected $requireProcessingForRender = true;
    protected $shouldBuildFields = true;
    protected $data;
    protected $processed = false;
    protected $fieldsBuilt = false;
    protected $fieldsContent;
    protected $validates;
    protected $validationAttempted = false;
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
        if (!$this->validationAttempted) {
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

        if ($this->validationAttempted) {
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

    public function addData($additionalData)
    {
        $this->data = array_merge($this->data, $additionalData);
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

        $this->fieldsBuilt = true;
    }

    public function setShouldBuildFields($shouldBuildFields)
    {
        $this->shouldBuildFields = $shouldBuildFields;
    }

    public function getContent($template = false, $additionalReplacements = false)
    {

        if (!$template) {
            $template = $this->template;
        }

        if ($this->requireProcessingForRender && !$this->processed) {
            $this->process();
        }

        if ($this->shouldBuildFields && !$this->fieldsBuilt) {
            $this->buildFields();
        }

        $replacements = $this->getData();
        if (is_array($additionalReplacements)) {
            $replacements = array_merge($replacements, $additionalReplacements);
        }

        $content = parent::getContent($template, $replacements);

        return $content;
    }
}
