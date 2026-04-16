// Custom commands for Precision Portal E2E tests

Cypress.Commands.add('login', (username, password) => {
  cy.request('POST', '/api/auth/login', { username, password }).then((resp) => {
    expect(resp.status).to.eq(200);
    expect(resp.body.data).to.have.property('role');
  });
});

Cypress.Commands.add('loginAsAdmin', () => {
  cy.login('admin', 'Admin12345!');
});

Cypress.Commands.add('loginAsCoordinator', () => {
  cy.login('coordinator1', 'Coordinator1!');
});

Cypress.Commands.add('loginAsProvider', () => {
  cy.login('provider1', 'Provider1234!');
});

Cypress.Commands.add('loginAsFinance', () => {
  cy.login('finance1', 'Finance12345!');
});

Cypress.Commands.add('loginAsModerator', () => {
  cy.login('moderator1', 'Moderator123!');
});

Cypress.Commands.add('loginAsReviewer', () => {
  cy.login('reviewer1', 'Reviewer1234!');
});

Cypress.Commands.add('loginAsPlanner', () => {
  cy.login('planner1', 'Planner12345!');
});

Cypress.Commands.add('apiPost', (url, body) => {
  return cy.request({ method: 'POST', url, body, failOnStatusCode: false });
});

Cypress.Commands.add('apiGet', (url) => {
  return cy.request({ method: 'GET', url, failOnStatusCode: false });
});

Cypress.Commands.add('apiPut', (url, body) => {
  return cy.request({ method: 'PUT', url, body, failOnStatusCode: false });
});
