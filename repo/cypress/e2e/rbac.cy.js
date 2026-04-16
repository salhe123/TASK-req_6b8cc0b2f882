describe('RBAC - Role-Based Access Control', () => {
  it('Provider cannot access admin endpoints', () => {
    cy.loginAsProvider();
    cy.apiGet('/api/admin/users').then((resp) => {
      expect(resp.status).to.eq(403);
    });
  });

  it('Provider cannot access finance endpoints', () => {
    cy.loginAsProvider();
    cy.apiGet('/api/finance/payments').then((resp) => {
      expect(resp.status).to.eq(403);
    });
  });

  it('Finance clerk cannot access production endpoints', () => {
    cy.loginAsFinance();
    cy.apiGet('/api/production/mps').then((resp) => {
      expect(resp.status).to.eq(403);
    });
  });

  it('Admin can access all endpoints', () => {
    cy.loginAsAdmin();

    cy.apiGet('/api/admin/users').then((resp) => {
      expect(resp.status).to.eq(200);
    });

    cy.apiGet('/api/admin/dashboard').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('totalUsers');
    });

    cy.apiGet('/api/admin/risk/flags').then((resp) => {
      expect(resp.status).to.eq(200);
    });

    cy.apiGet('/api/admin/audit/logs').then((resp) => {
      expect(resp.status).to.eq(200);
    });
  });

  it('Unauthenticated request gets 401', () => {
    // Clear cookies first
    cy.clearCookies();
    cy.apiGet('/api/admin/dashboard').then((resp) => {
      expect(resp.status).to.eq(401);
    });
  });

  it('Each role sees appropriate navigation', () => {
    cy.loginAsAdmin();
    cy.visit('/dashboard');
    cy.get('.pp-sidebar-nav').should('contain', 'Administration');
    cy.get('.pp-sidebar-nav').should('contain', 'Appointments');
    cy.get('.pp-sidebar-nav').should('contain', 'Production');
    cy.get('.pp-sidebar-nav').should('contain', 'Finance');
  });
});
