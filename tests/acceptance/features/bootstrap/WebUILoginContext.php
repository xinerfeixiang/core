<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\LoginPage;
use TestHelpers\EmailHelper;

require_once 'bootstrap.php';

/**
 * WebUI Login context.
 */
class WebUILoginContext extends RawMinkContext implements Context {
	private $loginFailedPageTitle = "ownCloud";
	private $loginSuccessPageTitle = "Files - ownCloud";
	private $loginPage;
	private $filesPage;
	private $expectedPage;
	
	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 *
	 * @var WebUIGeneralContext
	 */
	private $webUIGeneralContext;

	/**
	 * WebUILoginContext constructor.
	 *
	 * @param LoginPage $loginPage
	 */
	public function __construct(LoginPage $loginPage) {
		$this->loginPage = $loginPage;
	}

	/**
	 * @When the user browses to the login page
	 * @Given the user has browsed to the login page
	 *
	 * @return void
	 */
	public function theUserBrowsesToTheLoginPage() {
		$this->loginPage->open();
	}

	/**
	 * @When the user re-logs in with username :username and password :password using the webUI
	 * @Given the user has re-logged in with username :username and password :password using the webUI
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserRelogsInWithUsernameAndPasswordUsingTheWebUI(
		$username, $password
	) {
		$this->webUIGeneralContext->theUserLogsOutOfTheWebUI();
		$this->theUserLogsInWithUsernameAndPasswordUsingTheWebUI(
			$username, $password
		);
	}

	/**
	 * @When the user logs in with username :username and password :password using the webUI
	 * @Given the user has logged in with username :username and password :password using the webUI
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserLogsInWithUsernameAndPasswordUsingTheWebUI(
		$username, $password
	) {
		$this->filesPage = $this->webUIGeneralContext->loginAs($username, $password);
	}

	/**
	 * @When the user re-logs in with username :username and password :password to :server using the webUI
	 * @Given the user has re-logged in with username :username and password :password to :server using the webUI
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $server
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserRelogsInWithUsernameAndPasswordToUsingTheWebUI(
		$username, $password, $server
	) {
		$this->webUIGeneralContext->theUserLogsOutOfTheWebUI();
		$this->theUserLogsInWithUsernameAndPasswordToUsingTheWebUI(
			$username, $password, $server
		);
	}

	/**
	 * @When the user logs in with username :username and password :password to :server using the webUI
	 * @Given the user has logged in with username :username and password :password to :server using the webUI
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $server
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserLogsInWithUsernameAndPasswordToUsingTheWebUI(
		$username, $password, $server
	) {
		$server = $this->featureContext->substituteInLineCodes($server);
		$this->webUIGeneralContext->setCurrentServer($server);
		$this->loginPage->setPagePath(
			$server . $this->loginPage->getOriginalPath()
		);
		$this->loginPage->open();
		$this->theUserLogsInWithUsernameAndPasswordUsingTheWebUI(
			$username, $password
		);
	}

	/**
	 * @When the user logs in with username :username and invalid password :password using the webUI
	 * @When the user logs in with invalid username :username and password :password using the webUI
	 * @When the user logs in with invalid username :username and invalid password :password using the webUI
	 * @Given the user has logged in with username :username and invalid password :password using the webUI
	 * @Given the user has logged in with invalid username :username and password :password using the webUI
	 * @Given the user has logged in with invalid username :username and invalid password :password using the webUI
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserLogsInWithUsernameAndInvalidPasswordUsingTheWebUI(
		$username, $password
	) {
		$this->loginPage->loginAs($username, $password, 'LoginPage');
		$this->loginPage->waitTillPageIsLoaded($this->getSession());
	}

	/**
	 * @When the user logs in with username :username and password :password using the webUI after a redirect from the :page page
	 * @Given the user has logged in with username :username and password :password using the webUI after a redirect from the :page page
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $page text name of a page that I expect to be taken to
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserLogsInWithUsernameAndPasswordAfterRedirectFromThePage(
		$username,
		$password,
		$page
	) {
		$this->expectedPage = $this->webUIGeneralContext->loginAs(
			$username,
			$password,
			\str_replace(' ', '', \ucwords($page)) . 'Page'
		);
	}

	/**
	 * @Then /^it should (not|)\s?be possible to login with the username ((?:'[^']*')|(?:"[^"]*")) and password ((?:'[^']*')|(?:"[^"]*")) using the WebUI$/
	 *
	 * @param string $shouldOrNot
	 * @param string $username
	 * @param string $password
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function itShouldBePossibleToLogin($shouldOrNot, $username, $password) {
		$should = ($shouldOrNot !== "not");

		// The capturing groups of the regex include the quotes at each
		// end of the captured string, so trim them.
		if ($username !== "") {
			$username = \trim($username, $username[0]);
		}
		if ($password !== "") {
			$password = \trim($password, $password[0]);
		}
		$this->theUserBrowsesToTheLoginPage();
		if ($should) {
			$this->theUserLogsInWithUsernameAndPasswordUsingTheWebUI(
				$username, $password
			);
			$this->webUIGeneralContext->theUserShouldBeRedirectedToAWebUIPageWithTheTitle(
				$this->loginSuccessPageTitle
			);
		} else {
			$this->theUserLogsInWithUsernameAndInvalidPasswordUsingTheWebUI(
				$username, $password
			);
			$this->webUIGeneralContext->theUserShouldBeRedirectedToAWebUIPageWithTheTitle(
				$this->loginFailedPageTitle
			);
		}
	}

	/**
	 * @When the user requests the password reset link using the webUI
	 * @Given the user has requested the password reset link using the webUI
	 *
	 * @return void
	 */
	public function theUserRequestsThePasswordResetLinkUsingTheWebui() {
		$this->loginPage->requestPasswordReset($this->getSession());
	}

	/**
	 * @Then a message with this text should be displayed on the webUI:
	 *
	 * @param PyStringNode $string
	 *
	 * @return void
	 */
	public function thisMessageShouldBeDisplayed(PyStringNode $string) {
		$expectedString = $string->getRaw();
		$passwordRecoveryMessage = $this->loginPage->getLostPasswordMessage();
		PHPUnit_Framework_Assert::assertEquals(
			$expectedString, $passwordRecoveryMessage
		);
	}

	/**
	 * @When the user follows the password reset link from email address :emailAddress
	 * @Given the user has followed the password reset link from email address :emailAddress
	 *
	 * @param string $emailAddress
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserFollowsThePasswordResetLinkFromTheirEmail($emailAddress) {
		$this->webUIGeneralContext->followLinkFromEmail(
			$emailAddress,
			"/Use the following link to reset your password: (http.*)/",
			"Couldn't find password reset link in the email"
		);
	}

	/**
	 * @When the user resets/sets the password to :newPassword using the webUI
	 * @Given the user has reset/set the password to :newPassword using the webUI
	 *
	 * @param string $newPassword
	 *
	 * @return void
	 */
	public function theUserResetsThePasswordToUsingTheWebui($newPassword) {
		$this->loginPage->resetThePassword($newPassword, $this->getSession());
	}

	/**
	 * @When /^the user follows the password set link received by "([^"]*)"(?: in Email no (\d+))? using the webUI$/
	 *
	 * @param string $emailAddress
	 * @param int which number of multiple emails to read (first email is 1)
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserFollowsThePasswordSetLinkReceivedByEmail($emailAddress, $no=1) {
		$this->webUIGeneralContext->followLinkFromEmail(
			$emailAddress,
			"/Access it: (http.*)/",
			"Couldn't find password set link in the email",
			$no
		);
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario @webUI
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function before(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		$this->webUIGeneralContext = $environment->getContext('WebUIGeneralContext');
	}
}
