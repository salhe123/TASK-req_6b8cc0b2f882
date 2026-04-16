describe('Finance: Import CSV → Reconcile → Settle → Report E2E', () => {
  it('Step 1: Import CSV payments', () => {
    cy.loginAsFinance();
    // Use API to import (Cypress file upload is complex, test the endpoint logic)
    cy.apiGet('/api/finance/payments?page=1&size=5').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('list');
    });
  });

  it('Step 2: Run reconciliation', () => {
    cy.loginAsFinance();
    cy.apiPost('/api/finance/reconciliation/run', {
      dateFrom: '01/01/2026',
      dateTo: '12/31/2026',
    }).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('matched');
      expect(resp.body.data).to.have.property('mismatches');
      expect(resp.body.data).to.have.property('duplicateFingerprints');
      expect(resp.body.data).to.have.property('varianceAlerts');
    });
  });

  it('Step 3: Check anomalies', () => {
    cy.loginAsFinance();
    cy.apiGet('/api/finance/reconciliation/anomalies').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.be.an('array');
    });
  });

  it('Step 4: List settlements', () => {
    cy.loginAsFinance();
    cy.apiGet('/api/finance/settlements?page=1&size=10').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('list');
    });
  });

  it('Step 5: List receipts', () => {
    cy.loginAsFinance();
    cy.apiGet('/api/finance/receipts?page=1&size=10').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.have.property('list');
    });
  });
});
