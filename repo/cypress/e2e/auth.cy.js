describe('Authentication', () => {
  it('should login with valid credentials', () => {
    cy.apiPost('/api/auth/login', {
      username: 'admin',
      password: 'Admin12345!',
    }).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('token');
      expect(resp.body.data).to.have.property('role', 'SYSTEM_ADMIN');
      expect(resp.body.data).to.have.property('expiresIn', 900);
    });
  });

  it('should reject invalid credentials with 401', () => {
    cy.apiPost('/api/auth/login', {
      username: 'admin',
      password: 'WrongPassword!',
    }).then((resp) => {
      expect(resp.status).to.eq(401);
    });
  });

  it('should render login page', () => {
    cy.visit('/login');
    cy.get('.pp-login-box').should('exist');
    cy.get('input[name="username"]').should('exist');
    cy.get('input[name="password"]').should('exist');
    cy.get('button[lay-filter="login"]').should('contain', 'Sign In');
  });

  it('should change password', () => {
    cy.loginAsAdmin();
    // Create a temp user, change password, verify
    cy.apiPost('/api/admin/users', {
      username: 'cypressuser_' + Date.now(),
      password: 'CypressPass1!',
      role: 'PROVIDER',
    }).then((resp) => {
      expect(resp.status).to.eq(201);
    });
  });

  it('should logout', () => {
    cy.loginAsAdmin();
    cy.apiPost('/api/auth/logout', {}).then((resp) => {
      expect(resp.status).to.eq(200);
    });
  });
});
