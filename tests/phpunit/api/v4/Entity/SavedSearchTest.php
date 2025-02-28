<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */


namespace api\v4\Entity;

use api\v4\Api4TestBase;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\SavedSearch;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class SavedSearchTest extends Api4TestBase implements TransactionalInterface {

  /**
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   */
  public function testContactSmartGroup(): void {
    $in = Contact::create(FALSE)->addValue('first_name', 'yes')->addValue('do_not_phone', TRUE)->execute()->first();
    $out = Contact::create(FALSE)->addValue('first_name', 'no')->addValue('do_not_phone', FALSE)->execute()->first();

    $savedSearch = civicrm_api4('SavedSearch', 'create', [
      'values' => [
        'api_entity' => 'Contact',
        'api_params' => [
          'version' => 4,
          'where' => [
            ['do_not_phone', '=', TRUE],
          ],
        ],
      ],
      'chain' => [
        'group' => ['Group', 'create', ['values' => ['title' => 'Contact Test', 'saved_search_id' => '$id']], 0],
      ],
    ])->first();

    $ins = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertCount(1, $ins);
    $this->assertArrayHasKey($in['id'], (array) $ins);

    $outs = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'NOT IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertArrayHasKey($out['id'], (array) $outs);
    $this->assertArrayNotHasKey($in['id'], (array) $outs);
  }

  public function testEmailSmartGroup(): void {
    $in = Contact::create(FALSE)->addValue('first_name', 'yep')->execute()->first();
    $out = Contact::create(FALSE)->addValue('first_name', 'nope')->execute()->first();
    $email = uniqid() . '@' . uniqid();
    Email::create(FALSE)->addValue('email', $email)->addValue('contact_id', $in['id'])->execute();

    $savedSearch = civicrm_api4('SavedSearch', 'create', [
      'values' => [
        'api_entity' => 'Email',
        'api_params' => [
          'version' => 4,
          'select' => ['contact_id'],
          'where' => [
            ['email', '=', $email],
          ],
        ],
      ],
      'chain' => [
        'group' => ['Group', 'create', ['values' => ['title' => 'Email Test', 'saved_search_id' => '$id']], 0],
      ],
    ])->first();

    $ins = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertCount(1, $ins);
    $this->assertArrayHasKey($in['id'], (array) $ins);

    $outs = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'NOT IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertArrayHasKey($out['id'], (array) $outs);
    $this->assertArrayNotHasKey($in['id'], (array) $outs);
  }

  public function testSmartGroupWithHaving(): void {
    $in = Contact::create(FALSE)->addValue('first_name', 'yes')->addValue('last_name', 'siree')->execute()->first();
    $in2 = Contact::create(FALSE)->addValue('first_name', 'yessir')->addValue('last_name', 'ee')->execute()->first();
    $out = Contact::create(FALSE)->addValue('first_name', 'yess')->execute()->first();

    $savedSearch = civicrm_api4('SavedSearch', 'create', [
      'values' => [
        'api_entity' => 'Contact',
        'api_params' => [
          'version' => 4,
          'select' => ['id', 'CONCAT(first_name, last_name) AS whole_name'],
          'where' => [
            ['id', '>=', $in['id']],
          ],
          'having' => [
            ['whole_name', '=', 'yessiree'],
          ],
        ],
      ],
      'chain' => [
        'group' => ['Group', 'create', ['values' => ['title' => 'Having Test', 'saved_search_id' => '$id']], 0],
      ],
    ])->first();

    $ins = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertCount(2, $ins);
    $this->assertArrayHasKey($in['id'], (array) $ins);
    $this->assertArrayHasKey($in2['id'], (array) $ins);

    $outs = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'NOT IN', [$savedSearch['group']['id']]]],
    ])->indexBy('id');
    $this->assertArrayHasKey($out['id'], (array) $outs);
    $this->assertArrayNotHasKey($in['id'], (array) $outs);
    $this->assertArrayNotHasKey($in2['id'], (array) $outs);
  }

  public function testMultipleSmartGroups(): void {
    $inGroup = $outGroup = [];
    $inName = uniqid('inGroup');
    $outName = uniqid('outGroup');
    for ($i = 0; $i < 10; ++$i) {
      $inGroup[] = Contact::create(FALSE)
        ->setValues(['first_name' => "$i", 'last_name' => $inName])
        ->execute()->first()['id'];
      $outGroup[] = Contact::create(FALSE)
        ->setValues(['first_name' => "$i", 'last_name' => $outName])
        ->execute()->first()['id'];
    }

    $parentGroupId = \Civi\Api4\Group::create(FALSE)
      ->setValues(['title' => uniqid()])
      ->execute()->first()['id'];

    $savedSearchA = civicrm_api4('SavedSearch', 'create', [
      'values' => [
        'api_entity' => 'Contact',
        'api_params' => [
          'version' => 4,
          'where' => [
            ['last_name', '=', $inName],
          ],
        ],
      ],
      'chain' => [
        'group' => ['Group', 'create', ['values' => ['parents' => [$parentGroupId], 'title' => 'In A Test', 'saved_search_id' => '$id']], 0],
      ],
    ])->first();

    $savedSearchB = civicrm_api4('SavedSearch', 'create', [
      'values' => [
        'api_entity' => 'Contact',
        'api_params' => [
          'version' => 4,
          'where' => [
            ['last_name', 'IN', [$inName, $outName]],
            ['first_name', '>', '4'],
          ],
        ],
      ],
      'chain' => [
        'group' => ['Group', 'create', ['values' => ['parents' => [$parentGroupId], 'title' => 'In B Test', 'saved_search_id' => '$id']], 0],
      ],
    ])->first();

    $bothGroups = civicrm_api4('Contact', 'get', [
      'where' => [['groups:name', 'IN', [$savedSearchA['group']['name'], $savedSearchB['group']['name']]]],
    ]);
    $this->assertCount(15, $bothGroups);

    // Parent group includes both groups a & b so should give the same results as above
    $parentGroup = civicrm_api4('Contact', 'get', [
      'where' => [['groups', 'IN', [$parentGroupId]]],
    ]);
    $this->assertCount(15, $parentGroup);

    $aNotB = civicrm_api4('Contact', 'get', [
      'where' => [
        ['groups:name', 'IN', [$savedSearchA['group']['name']]],
        ['groups:name', 'NOT IN', [$savedSearchB['group']['name']]],
      ],
    ]);
    $this->assertCount(5, $aNotB);
  }

  public function testSearchTemplateGet(): void {
    $name = uniqid();
    $savedSearch = $this->createTestRecord('SavedSearch', [
      'name' => $name,
      'is_template' => TRUE,
    ]);

    // APIv4 automatically excludes is_template from normal GET
    $getWithout = SavedSearch::get(FALSE)
      ->setSelect(['id', 'name'])
      ->execute()->column('name', 'id');
    $this->assertArrayNotHasKey($savedSearch['id'], $getWithout);

    // Get by name will override that exclusion rule
    $getWith = SavedSearch::get(FALSE)
      ->addWhere('name', '=', $name)
      ->setSelect(['id', 'name'])
      ->execute()->column('name', 'id');
    $this->assertCount(1, $getWith);
    $this->assertArrayHasKey($savedSearch['id'], $getWith);
  }

}
