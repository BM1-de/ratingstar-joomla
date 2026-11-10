<?php

/**
 * @package     RatingStar.Module
 * @subpackage  mod_ratingstar
 *
 * @copyright   (C) Baumgärtner Marketing GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace RatingStar\Module\Ratingstar\Site\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Form rule for a RatingStar API key: "rs_live_" followed by 40 alphanumeric
 * characters. An empty value passes unless the field is required.
 *
 * @since  0.3.0
 */
class ApikeyRule extends FormRule
{
    /**
     * The regular expression to use in testing a form field value.
     *
     * @var  string
     */
    protected $regex = '^rs_live_[A-Za-z0-9]{40}$';

    /**
     * Tests the value of the API key field.
     *
     * @param   \SimpleXMLElement  $element  The field definition.
     * @param   mixed              $value    The field value.
     * @param   string|null        $group    The field name group control value.
     * @param   Registry|null      $input    The form input data.
     * @param   Form|null          $form     The form object.
     *
     * @return  boolean  True if the value is valid, false otherwise.
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $value    = trim((string) $value);
        $required = ((string) $element['required'] === 'true' || (string) $element['required'] === 'required');

        if ($value === '' && !$required) {
            return true;
        }

        return parent::test($element, $value, $group, $input, $form);
    }
}
