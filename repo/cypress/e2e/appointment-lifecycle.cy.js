describe('Appointment Lifecycle E2E', () => {
  let appointmentId;

  it('Step 1: Login as coordinator and create appointment', () => {
    cy.loginAsCoordinator();
    cy.apiPost('/api/appointments', {
      customerId: 3,
      providerId: 4,
      dateTime: '04/25/2026 02:30 PM',
      location: 'Building C, Room 101',
    }).then((resp) => {
      expect(resp.status).to.eq(201);
      expect(resp.body.data).to.have.property('status', 'PENDING');
      appointmentId = resp.body.data.id;
    });
  });

  it('Step 2: Confirm appointment', () => {
    cy.loginAsCoordinator();
    cy.apiPut(`/api/appointments/${appointmentId}/confirm`, {}).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('status', 'CONFIRMED');
    });
  });

  it('Step 3: Provider checks in', () => {
    cy.loginAsProvider();
    cy.apiPut(`/api/appointments/${appointmentId}/check-in`, {}).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('status', 'IN_PROGRESS');
    });
  });

  it('Step 4a: Upload completion evidence', () => {
    // Tiny 1x1 PNG as completion evidence — required before check-out.
    cy.loginAsProvider();
    const png = Cypress.Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
      'base64'
    );
    const formData = new FormData();
    formData.append('file', new Blob([png], { type: 'image/png' }), 'evidence.png');
    cy.request({
      method: 'POST',
      url: `/api/appointments/${appointmentId}/attachments`,
      body: formData,
    }).then((resp) => {
      expect([200, 201]).to.include(resp.status);
    });
  });

  it('Step 4b: Provider checks out after evidence uploaded', () => {
    cy.loginAsProvider();
    cy.apiPut(`/api/appointments/${appointmentId}/check-out`, {}).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('status', 'COMPLETED');
    });
  });

  it('Step 5: Verify immutable history', () => {
    cy.loginAsCoordinator();
    cy.apiGet(`/api/appointments/${appointmentId}/history`).then((resp) => {
      expect(resp.status).to.eq(200);
      const history = resp.body.data;
      expect(history.length).to.be.at.least(4);
      expect(history[0].to_status).to.eq('PENDING');
      expect(history[1].to_status).to.eq('CONFIRMED');
      expect(history[2].to_status).to.eq('IN_PROGRESS');
      expect(history[history.length - 1].to_status).to.eq('COMPLETED');
    });
  });

  it('Cannot cancel a COMPLETED appointment', () => {
    cy.loginAsCoordinator();
    cy.apiPut(`/api/appointments/${appointmentId}/cancel`, {
      reason: 'Should fail',
    }).then((resp) => {
      expect(resp.status).to.not.eq(200);
    });
  });
});
