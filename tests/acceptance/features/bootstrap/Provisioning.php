<?php
/**
 * @author Sergio Bertolin <sbertolin@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Message\ResponseInterface;

require __DIR__ . '/../../../../lib/composer/autoload.php';

/**
 * Functions for provisioning of users and groups
 */
trait Provisioning {

	/**
	 * @var array 
	 */
	private $createdUsers = [];

	/**
	 * @var array 
	 */
	private $createdRemoteUsers = [];

	/**
	 * @var array 
	 */
	private $createdRemoteGroups = [];

	/**
	 * @var array 
	 */
	private $createdGroups = [];

	/**
	 * @When /^the administrator creates the user "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been created$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function adminCreatesUserUsingTheAPI($user) {
		if (!$this->userExists($user) ) {
			$previous_user = $this->currentUser;
			$this->currentUser = $this->getAdminUserName();
			$this->createTheUserUsingTheAPI($user);
			$this->currentUser = $previous_user;
		}
		PHPUnit_Framework_Assert::assertTrue($this->userExists($user));
	}

	/**
	 * @Then /^user "([^"]*)" should exist$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userShouldExist($user) {
		PHPUnit_Framework_Assert::assertTrue($this->userExists($user));
		$this->rememberTheUser($user);
	}

	/**
	 * @Then /^user "([^"]*)" should not exist$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userShouldNotExist($user) {
		PHPUnit_Framework_Assert::assertFalse($this->userExists($user));
	}

	/**
	 * @Then /^group "([^"]*)" should exist$/
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function groupShouldExist($group) {
		PHPUnit_Framework_Assert::assertTrue($this->groupExists($group));
		$this->rememberTheGroup($group);
	}

	/**
	 * @Then /^group "([^"]*)" should not exist$/
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function groupShouldNotExist($group) {
		PHPUnit_Framework_Assert::assertFalse($this->groupExists($group));
	}

	/**
	 * @When /^the administrator deletes user "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been deleted$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function adminDeletesUserUsingTheAPI($user) {
		if ($this->userExists($user)) {
			$previous_user = $this->currentUser;
			$this->currentUser = $this->getAdminUserName();
			$this->deleteTheUserUsingTheAPI($user);
			$this->currentUser = $previous_user;
		}
		PHPUnit_Framework_Assert::assertFalse($this->userExists($user));
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 */
	public function rememberTheUser($user) {
		if ($this->currentServer === 'LOCAL') {
			$this->createdUsers[$user] = $user;
		} elseif ($this->currentServer === 'REMOTE') {
			$this->createdRemoteUsers[$user] = $user;
		}
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 */
	public function createTheUserUsingTheAPI($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$password = $this->getPasswordForUser($user);
		$options['body'] = [
							'userid' => $user,
							'password' => $password
							];

		$this->response = $client->send(
			$client->createRequest("POST", $fullUrl, $options)
		);
		$this->rememberTheUser($user);

		//Quick hack to login once with the current user
		$options2 = [
			'auth' => [$user, $password],
		];
		$url = $fullUrl . '/' . $user;
		$client->send($client->createRequest('GET', $url, $options2));
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 */
	public function createUser($user) {
		$previous_user = $this->currentUser;
		$this->currentUser = $this->getAdminUserName();
		$this->createTheUserUsingTheAPI($user);
		PHPUnit_Framework_Assert::assertTrue($this->userExists($user));
		$this->currentUser = $previous_user;
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 */
	public function deleteUser($user) {
		$previous_user = $this->currentUser;
		$this->currentUser = $this->getAdminUserName();
		$this->deleteTheUserUsingTheAPI($user);
		PHPUnit_Framework_Assert::assertFalse($this->userExists($user));
		$this->currentUser = $previous_user;
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public function createGroup($group) {
		$previous_user = $this->currentUser;
		$this->currentUser = $this->getAdminUserName();
		$this->createTheGroup($group);
		PHPUnit_Framework_Assert::assertTrue($this->groupExists($group));
		$this->currentUser = $previous_user;
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public function deleteGroup($group) {
		$previous_user = $this->currentUser;
		$this->currentUser = $this->getAdminUserName();
		$this->deleteTheGroupUsingTheAPI($group);
		PHPUnit_Framework_Assert::assertFalse($this->groupExists($group));
		$this->currentUser = $previous_user;
	}

	/**
	 * @param string $user
	 *
	 * @return bool
	 */
	public function userExists($user) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();
		try {
			$this->response = $client->get($fullUrl, $options);
			return true;
		} catch (BadResponseException $e) {
			$this->response = $e->getResponse();
			return false;
		}
	}

	/**
	 * @Then /^user "([^"]*)" should belong to group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function userShouldBelongToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfGroupsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertContains($group, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @Then /^user "([^"]*)" should not belong to group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function userShouldNotBelongToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfGroupsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertNotContains($group, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @param string $user
	 * @param string $group
	 *
	 * @return bool
	 */
	public function userBelongsToGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfGroupsResponded($this->response);

		if (in_array($group, $respondedArray)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @When /^the administrator adds user "([^"]*)" to group "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been added to group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function adminAddsUserToGroupUsingTheAPI($user, $group) {
		$previous_user = $this->currentUser;
		$this->currentUser = $this->getAdminUserName();

		if (!$this->userBelongsToGroup($user, $group)) {
			$this->addUserToGroupUsingTheAPI($user, $group);
		}

		$this->userShouldBelongToGroup($user, $group);
		$this->currentUser = $previous_user;
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public function rememberTheGroup($group) {
		if ($this->currentServer === 'LOCAL') {
			$this->createdGroups[$group] = $group;
		} elseif ($this->currentServer === 'REMOTE') {
			$this->createdRemoteGroups[$group] = $group;
		}
	}

	/**
	 * @When /^the administrator creates group "([^"]*)" using the API$/
	 * @Given /^group "([^"]*)" has been created$/
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function adminCreatesGroupUsingTheAPI($group) {
		if (!$this->groupExists($group)) {
			$previous_user = $this->currentUser;
			$this->currentUser = $this->getAdminUserName();
			$this->createTheGroup($group);
			$this->currentUser = $previous_user;
		}
		PHPUnit_Framework_Assert::assertTrue($this->groupExists($group));
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public function createTheGroup($group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$options['body'] = [
			'groupid' => $group,
		];

		$this->response = $client->send(
			$client->createRequest("POST", $fullUrl, $options)
		);
		$this->rememberTheGroup($group);
	}

	/**
	 * @When /^the administrator disables user "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been disabled$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function adminDisablesUserUsingTheAPI($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user/disable";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();

		$this->response = $client->send(
			$client->createRequest("PUT", $fullUrl, $options)
		);
	}

	/**
	 * @param string $user
	 *
	 * @return void
	 */
	public function deleteTheUserUsingTheAPI($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->send(
			$client->createRequest("DELETE", $fullUrl, $options)
		);
	}

	/**
	 * @When /^the administrator deletes group "([^"]*)" using the API$/
	 * @Given /^group "([^"]*)" has been deleted$/
	 *
	 * @param string $group
	 *
	 * @return void
	 */
	public function adminDeletesGroupUsingTheAPI($group) {
		if ($this->groupExists($group)) {
			$previous_user = $this->currentUser;
			$this->currentUser = $this->getAdminUserName();
			$this->deleteTheGroupUsingTheAPI($group);
			$this->currentUser = $previous_user;
		}
		PHPUnit_Framework_Assert::assertFalse($this->groupExists($group));
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public function deleteTheGroupUsingTheAPI($group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/groups/$group";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->send(
			$client->createRequest("DELETE", $fullUrl, $options)
		);
	}

	/**
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function addUserToGroupUsingTheAPI($user, $group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user/groups";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$options['body'] = [
							'groupid' => $group,
							];

		$this->response = $client->send(
			$client->createRequest("POST", $fullUrl, $options)
		);
	}

	/**
	 * @param string $group
	 *
	 * @return bool
	 */
	public function groupExists($group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();
		try {
			$this->response = $client->get($fullUrl, $options);
			return true;
		} catch (BadResponseException $e) {
			$this->response = $e->getResponse();
			return false;
		}
	}

	/**
	 * @Then /^user "([^"]*)" should be a subadmin of group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function userShouldBeSubadminOfGroup($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group/subadmins";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertContains($user, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @When /^the administrator makes user "([^"]*)" a subadmin of group "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been made a subadmin of group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function adminMakesUserSubadminOfGroupUsingTheAPI($user, $group) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user/subadmins";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();
		$options['body'] = [
							'groupid' => $group
							];
		$this->response = $client->send(
			$client->createRequest("POST", $fullUrl, $options)
		);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @When /^the administrator makes user "([^"]*)" not a subadmin of group "([^"]*)" using the API$/
	 * @Given /^user "([^"]*)" has been made not a subadmin of group "([^"]*)"$/
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return void
	 */
	public function adminMakesUserNotSubadminOfGroupUsingTheAPI($user, $group) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/groups/$group/subadmins";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
		sort($respondedArray);
		PHPUnit_Framework_Assert::assertNotContains($user, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @Then /^the users returned by the API should be$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $usersList
	 *
	 * @return void
	 */
	public function theUsersShouldBe($usersList) {
		if ($usersList instanceof \Behat\Gherkin\Node\TableNode) {
			$users = $usersList->getRows();
			$usersSimplified = $this->simplifyArray($users);
			$respondedArray = $this->getArrayOfUsersResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals(
				$usersSimplified, $respondedArray, "", 0.0, 10, true
			);
		}

	}

	/**
	 * @Then /^the groups returned by the API should be$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $groupsList
	 *
	 * @return void
	 */
	public function theGroupsShouldBe($groupsList) {
		if ($groupsList instanceof \Behat\Gherkin\Node\TableNode) {
			$groups = $groupsList->getRows();
			$groupsSimplified = $this->simplifyArray($groups);
			$respondedArray = $this->getArrayOfGroupsResponded($this->response);
			PHPUnit_Framework_Assert::assertEquals(
				$groupsSimplified, $respondedArray, "", 0.0, 10, true
			);
		}

	}

	/**
	 * @param \Behat\Gherkin\Node\TableNode|null $groupsOrUsersList
	 *
	 * @return void
	 */
	public function checkSubadminGroupsOrUsersTable($groupsOrUsersList) {
		$tableRows = $groupsOrUsersList->getRows();
		$simplifiedTableRows = $this->simplifyArray($tableRows);
		$respondedArray = $this->getArrayOfSubadminsResponded($this->response);
		PHPUnit_Framework_Assert::assertEquals(
			$simplifiedTableRows, $respondedArray, "", 0.0, 10, true
		);
	}

	/**
	 * @Then /^the subadmin groups returned by the API should be$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $groupsList
	 *
	 * @return void
	 */
	public function theSubadminGroupsShouldBe($groupsList) {
		$this->checkSubadminGroupsOrUsersTable($groupsList);
	}

	/**
	 * @Then /^the subadmin users returned by the API should be$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $usersList
	 *
	 * @return void
	 */
	public function theSubadminUsersShouldBe($usersList) {
		$this->checkSubadminGroupsOrUsersTable($usersList);
	}

	/**
	 * @Then /^the apps returned by the API should include$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $appList
	 *
	 * @return void
	 */
	public function theAppsShouldInclude($appList) {
		$apps = $appList->getRows();
		$appsSimplified = $this->simplifyArray($apps);
		$respondedArray = $this->getArrayOfAppsResponded($this->response);
		foreach ($appsSimplified as $app) {
			PHPUnit_Framework_Assert::assertContains($app, $respondedArray);
		}
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 *
	 * @param ResponseInterface $resp
	 *
	 * @return array
	 */
	public function getArrayOfUsersResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->users[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of groups returned.
	 *
	 * @param ResponseInterface $resp
	 *
	 * @return array
	 */
	public function getArrayOfGroupsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->groups[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of apps returned.
	 *
	 * @param ResponseInterface $resp
	 *
	 * @return array
	 */
	public function getArrayOfAppsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->apps[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * Parses the xml answer to get the array of subadmins returned.
	 *
	 * @param ResponseInterface $resp
	 *
	 * @return array
	 */
	public function getArrayOfSubadminsResponded($resp) {
		$listCheckedElements = $resp->xml()->data[0]->element;
		$extractedElementsArray = json_decode(json_encode($listCheckedElements), 1);
		return $extractedElementsArray;
	}

	/**
	 * @Then /^app "([^"]*)" should be disabled$/
	 *
	 * @param string $app
	 *
	 * @return void
	 */
	public function appShouldBeDisabled($app) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/apps?filter=disabled";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfAppsResponded($this->response);
		PHPUnit_Framework_Assert::assertContains($app, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @Then /^app "([^"]*)" should be enabled$/
	 *
	 * @param string $app
	 *
	 * @return void
	 */
	public function appShouldBeEnabled($app) {
		$fullUrl = $this->baseUrl . "v2.php/cloud/apps?filter=enabled";
		$client = new Client();
		$options = [];
		if ($this->currentUser === $this->getAdminUserName()) {
			$options['auth'] = $this->getAuthOptionForAdmin();
		}

		$this->response = $client->get($fullUrl, $options);
		$respondedArray = $this->getArrayOfAppsResponded($this->response);
		PHPUnit_Framework_Assert::assertContains($app, $respondedArray);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @Then /^user "([^"]*)" should be disabled$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userShouldBeDisabled($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();

		$this->response = $client->get($fullUrl, $options);
		PHPUnit_Framework_Assert::assertEquals(
			"false", $this->response->xml()->data[0]->enabled
		);
	}

	/**
	 * @Then /^user "([^"]*)" should be enabled$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function useShouldBeEnabled($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();

		$this->response = $client->get($fullUrl, $options);
		PHPUnit_Framework_Assert::assertEquals(
			"true", $this->response->xml()->data[0]->enabled
		);
	}

	/**
	 * @When the administrator sets the quota of user :user to :quota using the API
	 * @Given the quota of user :user has been set to :quota
	 *
	 * @param string $user
	 * @param string $quota
	 *
	 * @return void
	 */
	public function adminSetsUserQuotaToUsingTheAPI($user, $quota) {
		$body = new \Behat\Gherkin\Node\TableNode(
			[
			0 => ['key', 'quota'],
			1 => ['value', $quota],
			]
		);

		$previous_user = $this->currentUser;
		$this->currentUser = "admin";
		// method used from BasicStructure trait
		$this->sendingToWith("PUT", "/cloud/users/" . $user, $body);
		$this->currentUser = $previous_user;
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->response->getStatusCode()
		);
	}

	/**
	 * @When the administrator gives unlimited quota to user :user using the API
	 * @Given user :user has been given unlimited quota
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function adminGivesUnlimitedQuotaToUserUsingTheAPI($user) {
		$this->adminSetsUserQuotaToUsingTheAPI($user, 'none');
	}

	/**
	 * Returns home path of the given user
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function getUserHome($user) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/cloud/users/$user";
		$client = new Client();
		$options = [];
		$options['auth'] = $this->getAuthOptionForAdmin();
		$this->response = $client->get($fullUrl, $options);
		return $this->response->xml()->data[0]->home;
	}

	/**
	 * @Then /^the user attributes returned by the API should include$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $body
	 *
	 * @return void
	 */
	public function checkUserAttributes($body) {
		$data = $this->response->xml()->data[0];
		$fd = $body->getRowsHash();
		foreach ($fd as $field => $value) {
			if ($data->$field != $value) {
				PHPUnit_Framework_Assert::fail(
					"$field" . " has value " . "$data->$field"
				);
			}
		}
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 *
	 * @return void
	 */
	public function cleanupUsers() {
		$previousServer = $this->currentServer;
		$this->usingServer('LOCAL');
		foreach ($this->createdUsers as $user) {
			$this->deleteUser($user);
		}
		$this->usingServer('REMOTE');
		foreach ($this->createdRemoteUsers as $remoteUser) {
			$this->deleteUser($remoteUser);
		}
		$this->usingServer($previousServer);
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 *
	 * @return void
	 */
	public function cleanupGroups() {
		$previousServer = $this->currentServer;
		$this->usingServer('LOCAL');
		foreach ($this->createdGroups as $group) {
			$this->deleteGroup($group);
		}
		$this->usingServer('REMOTE');
		foreach ($this->createdRemoteGroups as $remoteGroup) {
			$this->deleteGroup($remoteGroup);
		}
		$this->usingServer($previousServer);
	}

}
