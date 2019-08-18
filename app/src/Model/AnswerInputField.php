<?php

/**
 * This file contains the "AnswerInputField" class.
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author  Catalyst I.T. SilverStripe Team 2018 <silverstripedev@catalyst.net.nz>
 * @copyright 2018 Catalyst.Net Ltd
 * @license https://www.catalyst.net.nz (Commercial)
 * @link https://www.catalyst.net.nz
 */

namespace NZTA\SDLT\Model;

use SilverStripe\GraphQL\Scaffolding\Interfaces\ScaffoldingProvider;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use UncleCheese\DisplayLogic\Forms\Wrapper;
use NZTA\SDLT\Traits\SDLTModelPermissions;
use NZTA\SDLT\Model\MultiChoiceAnswerSelection;

/**
 * Class AnswerInputField
 *
 * @property string Name
 * @property string Type
 * @property Question Question
 */
class AnswerInputField extends DataObject implements ScaffoldingProvider
{
    use SDLTModelPermissions;

    /**
     * @var string
     */
    private static $table_name = 'AnswerInputField';

    /**
     * @var array
     */
    private static $db = [
        'Label' => 'Varchar(255)',
        'InputType' => 'Enum("text, email, textarea, date, url, multiple-choice: single selection, multiple-choice: multiple selection", "text")',
        'Required' => 'Boolean',
        'MinLength' => 'Int',
        'PlaceHolder' => 'Varchar(255)',
        'SortOrder' => 'Int',
        'IsBusinessOwner' => 'Boolean',
        'IsProductName' => 'Boolean',
        'IsBusinessOwnerName' => 'Boolean',
        'MultiChoiceSingleAnswerDefault' => 'Varchar(255)',
        'MultiChoiceMultipleAnswerDefault' => 'Varchar(255)',
    ];

    /**
     * @var string
     */
    private static $default_sort = 'SortOrder';

    /**
     * @var array
     */
    private static $has_one = [
        'Question' => Question::class
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'AnswerSelections' => MultiChoiceAnswerSelection::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Label',
        'InputType'
    ];

    /**
     * @var array
     */
    private static $field_labels = [
        'Label' => 'Field Label',
        'InputType' => 'Field Type'
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'QuestionID',
            'SortOrder',
            'MultiChoiceSingleAnswerDefault',
            'MultiChoiceMultipleAnswerDefault',
        ]);

        $multiChoiceAnswerValues = $this->AnswerSelections()
            ->map('Label', 'Label')
            ->toArray();
        $blocksField = $fields->dataFieldByName('AnswerSelections');
        $fields->removeByName('AnswerSelections'); // <-- Removes the scaffolded tab

        if ($this->exists()) {
            $config = $blocksField->getConfig();
            $config->removeComponent($config->getComponentByType(GridFieldAddExistingAutocompleter::class));
            $fields->addFieldToTab('Root.Main', Wrapper::create($blocksField)
                ->hideUnless('InputType')
                ->startsWith('multiple-choice')
                ->end()
            );
        }

        // Multi-choice default selection fields
        $fields->insertAfter(
            'InputType',
            Wrapper::create(
                FieldList::create([
                    DropdownField::create(
                        'MultiChoiceSingleAnswerDefault',
                        'Radio Button Default Selection',
                        $multiChoiceAnswerValues ?: []
                    )
                        ->setEmptyString('(none)')
                        ->setDescription(''
                            . 'This selection represents which of the related '
                            . 'question\'s radio buttons, is selected by default. Once values have '
                            . 'been added, a default can be chosen.'
                        )
                        ->setDisabled(!$multiChoiceAnswerValues)
                        ->setAttribute('style', 'width: 200px;')
                        ->hideIf('InputType')
                        ->startsWith('multiple-choice: multiple')
                        ->end(),
                    Wrapper::create(ListboxField::create(
                        'MultiChoiceMultipleAnswerDefault',
                        'Checkbox Default Selections',
                        $multiChoiceAnswerValues ?: []
                    )
                        ->setDisabled(!$multiChoiceAnswerValues)
                        ->setDescription(''
                            . 'These selections represent which of the related '
                            . 'question\'s checkboxes are checked by default. '
                            . 'Once values have been added below, defaults can be chosen.'
                        ))
                            ->hideUnless('InputType')
                            ->startsWith('multiple-choice: multiple')
                            ->end()
                    ])
                )
                    ->displayIf('InputType')
                    ->startsWith('multiple-choice:')
                    ->end()
        );

        /** @noinspection PhpUndefinedMethodInspection */
        $fields->dataFieldByName('IsBusinessOwner')
            ->displayIf('InputType')
            ->isEqualTo('email');
        $fields->dataFieldByName('IsProductName')
            ->setTitle('Does this field contain a product name?')
            ->displayIf('InputType')
            ->isEqualTo('text');
        $fields->dataFieldByName('IsBusinessOwnerName')
            ->setTitle('Does this field contain a Business Owner name?')
            ->displayIf('InputType')
            ->isEqualTo('text');
        $fields->dataFieldByName('MinLength')
            ->displayUnless('InputType')
            ->startsWith('multiple-choice');
        $fields->dataFieldByName('PlaceHolder')
            ->displayUnless('InputType')
            ->startsWith('multiple-choice');

        return $fields;
    }

    /**
     * @param SchemaScaffolder $scaffolder Scaffolder
     * @return SchemaScaffolder
     */
    public function provideGraphQLScaffolding(SchemaScaffolder $scaffolder)
    {
        // Provide entity type
        $scaffolder
            ->type(AnswerInputField::class)
            ->addFields([
                'ID',
                'Label',
                'InputType',
                'Required',
                'MinLength',
                'GQLMultiChoiceAnswer' => 'Contains json-encoded, serialized data, representing multiple-choice answers.',
                'MultiChoiceSingleAnswerDefault' => 'An integer representing the default, single-selection, multiple-choice option.',
                'MultiChoiceMultipleAnswerDefault' => 'Contains json-encoded, serialized data, representing default multi-selections.',
            ]);

        return $scaffolder;
    }

    /**
     * OverLoaded getter for the "MultiChoiceAnswer" field. See the following issue
     * on GH for context for why this is needed: https://github.com/silverstripe/silverstripe-graphql/issues/234.
     *
     * @return string A JSON-encoded string of field label/values.
     */
    public function getGQLMultiChoiceAnswer()
    {
        $selections = $this->AnswerSelections();
        $optionData = [];

        if ($selections->exists()) {
            foreach ($selections as $selection) {
                $data['value'] = $selection->Label;         // "Value" == "Label" for default UI selections
                $data['calc_value'] = $selection->Value;    // Actual value is required to fetch from QuestionnaireData JSON blob
                $data['label'] = $selection->Label;

                //ensure the Risks key always exists as an array
                $risks = $selection->Risks()->toNestedArray();
                if ($risks) {
                    //avoid un-needed fields from JSON response
                    //reduces network payload and avoids info disclosure
                    foreach ($risks as $idx => $risk) {
                        unset($risk['ClassName'], $risk['LastEdited'], $risk['Created'], $risk['RecordClassName']);
                        $risks[$idx] = $risk;
                    }
                }
                $data['Risks'] = $risks ?: [];

                $optionData[] = $data;
            }
        }

        return json_encode($optionData);
    }

    /**
     * @return boolean
     */
    public function isMultipleChoice() : bool
    {
        return strstr($this->InputType, 'multiple-choice');
    }

    /**
     * @return boolean
     */
    public function isMultipleChoiceSingle() : bool
    {
        if (!$this->isMultipleChoice()) {
            return false;
        }

        return $this->InputType === 'multiple-choice: single selection';
    }

    /**
     * Allow logged-in user to access the model
     *
     * @param Member|null $member member
     * @return bool
     */
    public function canView($member = null)
    {
        return (Security::getCurrentUser() !== null);
    }

    /**
     * Return all the {@link Risk} objects related to an answer.
     *
     * @return array
     */
    public function getRisks() : array
    {
        $risks = [];

        foreach ($this->AnswerSelections() as $selection) {
            foreach ($selection->Risks() as $risk) {
                $risks[] = $risk;
            }
        }

        return $risks;
    }
}
