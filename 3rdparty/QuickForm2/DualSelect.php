<?php
/*
 * Usage example for HTML_QuickForm2 package: custom element and renderer plugin
 *
 * The example demonstrates a custom element with special rendering needs and
 * shows how it can be output via a renderer plugin. The example element is
 * a (much) simpler version of HTML_QuickForm_advmultiselect.
 *
 * It also demonstrates how to plug in element's javascript and how to use
 * client-side validation with custom element.
 *
 * $Id: dualselect.php 325702 2012-05-15 17:41:42Z avb $
 */

require_once 'HTML/QuickForm2.php';
require_once 'HTML/QuickForm2/Element/Select.php';
require_once 'HTML/QuickForm2/Renderer.php';
require_once 'HTML/QuickForm2/Renderer/Plugin.php';

/**
 * "Dualselect" element
 *
 * This element can be used instead of a normal multiple select. It renders as
 * two multiple selects and two buttons for moving options between them.
 * The values that end up in the "to" select are considered selected.
 */
class HTML_QuickForm2_Element_DualSelect extends HTML_QuickForm2_Element_Select
{
    protected $attributes = array('multiple' => 'multiple');

    protected $watchedAttributes = array('id', 'name', 'multiple');

    protected function onAttributeChange($name, $value = null)
    {
        if ('multiple' == $name && 'multiple' != $value) {
            throw new HTML_QuickForm2_InvalidArgumentException(
                "Required 'multiple' attribute cannot be changed"
            );
        }
        parent::onAttributeChange($name, $value);
    }

    public function __toString()
    {
        if ($this->frozen) {
            return $this->getFrozenHtml();
        } else {
            require_once 'HTML/QuickForm2/Renderer.php';

            $renderer = HTML_QuickForm2_Renderer::factory('default');

            $template =
                 "<table class=\"dualselect\" id=\"{id}\">\n" .
                 "    <tr>\n" .
                 "       <td style=\"vertical-align: top;\">{select_from}</td>\n" .
                 "       <td style=\"vertical-align: middle;\">{button_from_to}<br />{button_to_from}</td>\n" .
                 "       <td style=\"vertical-align: top;\">{select_to}</td>\n" .
                 "    </tr>\n" .
                 "</table>";

            $renderer->setTemplateForId($this->getId(), $template);
            return $this->render($renderer)->__toString();
        }
    }

    public function render(HTML_QuickForm2_Renderer $renderer)
    {
        // render as a normal select when frozen
        if ($this->frozen) {
            $renderer->renderElement($this);
        } else {
            $jsBuilder = $renderer->getJavascriptBuilder();
            $this->renderClientRules($jsBuilder);
            $jsBuilder->addLibrary('dualselect', 'dualselect.js', 'js/',
                                   dirname(__FILE__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR);
            $keepSorted = empty($this->data['keepSorted'])? 'false': 'true';
            $jsBuilder->addElementJavascript("qf.elements.dualselect.init('{$this->getId()}', {$keepSorted});");
            // Fall back to using the Default renderer if custom one does not have a plugin
            if ($renderer->methodExists('renderDualSelect')) {
                $renderer->renderDualSelect($this);
            } else {
                $renderer->renderElement($this);
            }
        }
        return $renderer;
    }

    public function toArray()
    {
        $id    = $this->getId();
        $name = $this->getName();

        $selectFrom = new HTML_QuickForm2_Element_Select(
            "_{$name}", array('id' => "{$id}-from") + $this->attributes
        );
        $selectTo   = new HTML_QuickForm2_Element_Select(
            $name, array('id' => "{$id}-to") + $this->attributes
        );
        $strValues = array_map('strval', $this->values);
        foreach ($this->optionContainer as $option) {
            // We don't do optgroups here
            if (!is_array($option)) {
                continue;
            }
            $value = $option['attr']['value'];
            unset($option['attr']['value']);
            if (in_array($value, $strValues, true)) {
                $selectTo->addOption($option['text'], $value,
                                     empty($option['attr'])? null: $option['attr']);
            } else {
                $selectFrom->addOption($option['text'], $value,
                                       empty($option['attr'])? null: $option['attr']);
            }
        }

        $buttonFromTo = HTML_QuickForm2_Factory::createElement(
            'button', "{$name}_fromto",
            array('type' => 'button', 'id' => "{$id}-fromto") +
                (empty($this->data['from_to']['attributes'])? array(): self::prepareAttributes($this->data['from_to']['attributes'])),
            array('content' => (empty($this->data['from_to']['content'])? ' &gt;&gt; ': $this->data['from_to']['content']))
        );
        $buttonToFrom = HTML_QuickForm2_Factory::createElement(
            'button', "{$name}_tofrom",
            array('type' => 'button', 'id' => "{$id}-tofrom") +
                (empty($this->data['to_from']['attributes'])? array(): self::prepareAttributes($this->data['to_from']['attributes'])),
            array('content' => (empty($this->data['to_from']['content'])? ' &lt;&lt; ': $this->data['to_from']['content']))
        );
        return array(
            'select_from'    => $selectFrom->__toString(),   'select_to'      => $selectTo->__toString(),
            'button_from_to' => $buttonFromTo->__toString(), 'button_to_from' => $buttonToFrom->__toString()
        );
    }

   /**
    * Returns Javascript code for getting the element's value
    *
    * All options in "to" select are considered dualselect's values,
    * we need to use an implementation different from that for a standard
    * select-multiple. When returning a parameter for getContainerValue()
    * we should also provide the element's name.
    *
    * @param  bool  Whether it should return a parameter for qf.form.getContainerValue()
    * @return   string
    */
    public function getJavascriptValue($inContainer = false)
    {
        if ($inContainer) {
            return "{name: '{$this->getName()}[]', value: qf.elements.dualselect.getValue('{$this->getId()}-to')}";
        } else {
            return "qf.elements.dualselect.getValue('{$this->getId()}-to')";
        }
    }

    public function getJavascriptTriggers()
    {
        $id = $this->getId();
        return array("{$id}-from", "{$id}-to", "{$id}-fromto", "{$id}-tofrom");
    }
}

/**
 * Renderer plugin for outputting dualselect
 *
 * A plugin is needed since we want to control outputting the selects and
 * buttons via the template. Also default template contains placeholders for
 * two additional labels.
 */
class HTML_QuickForm2_Renderer_Default_DualSelectPlugin
    extends HTML_QuickForm2_Renderer_Plugin
{
    public function setRenderer(HTML_QuickForm2_Renderer $renderer)
    {
        parent::setRenderer($renderer);
        if (empty($this->renderer->templatesForClass['html_quickform2_element_dualselect'])) {
            $this->renderer->templatesForClass['html_quickform2_element_dualselect'] = <<<TPL
<div class="row">
    <p class="label">
      <qf:required><span class="required">* </span></qf:required>
      <qf:label><label for="{id}">{label}</label></qf:label>
    </p>
    <div class="element<qf:error> error</qf:error>">
        <qf:error><span class="error">{error}<br /></span></qf:error>
        <table class="dualselect" id="{id}">
            <tr>
                <td style="vertical-align: top;">{select_from}</td>
                <td style="vertical-align: middle;">{button_from_to}<br />{button_to_from}</td>
                <td style="vertical-align: top;">{select_to}</td>
            </tr>
            <qf:label_2>
            <qf:label_3>
            <tr>
                <th>{label_2}</th>
                <th>&nbsp;</th>
                <th>{label_3}</th>
            </tr>
            </qf:label_3>
            </qf:label_2>
        </table>
    </div>
</div>
TPL;
        }
        if (false) {
            if (empty($this->renderer->templatesForGroupClass['html_quickform2_element_dualselect'])) {
                $this->renderer->setElementTemplateForGroupClass(
                    'HTML_QuickForm2_Container_Group', 'html_quickform2_element_dualselect',
                    $this->renderer->templatesForClass['html_quickform2_element_dualselect']);
            }
        }
    }

    public function renderDualSelect(HTML_QuickForm2_Node $element)
    {
        $elTpl = $this->renderer->prepareTemplate($this->renderer->findTemplate($element), $element);
        foreach ($element->toArray() as $k => $v) {
            $elTpl = str_replace('{' . $k . '}', $v, $elTpl);
        }
        $this->renderer->html[count($this->renderer->html) - 1][] = str_replace('{id}', $element->getId(), $elTpl);
    }
}

// Now we register both the element and the renderer plugin
HTML_QuickForm2_Factory::registerElement('dualselect', 'HTML_QuickForm2_Element_DualSelect');
HTML_QuickForm2_Renderer::registerPlugin('default', 'HTML_QuickForm2_Renderer_Default_DualSelectPlugin');
?>

