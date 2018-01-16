import {$, $$, browser, by, By, element, ExpectedConditions} from 'protractor';

export class BellowsForgotPasswordPage {
  get = function() {
    browser.get(browser.baseUrl + '/auth/forgot_password');
  };

  form = element(by.id('forgot-password-form'));
  // infoMessage and errors are dynamic elements, so class name locators seem to be the best option
  infoMessages = element.all(by.className('alert-info'));
  errors = element.all(by.className('alert-danger'));
  usernameInput = element(by.id('username'));
  submitButton = element(by.id('forgot-password-submit-btn'));
}

module.exports = new BellowsForgotPasswordPage();
