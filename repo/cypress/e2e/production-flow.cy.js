describe('Production: MPS → Explode → Complete → Capacity E2E', () => {
  let mpsId;
  let workOrderId;

  it('Step 1: List work centers', () => {
    cy.loginAsPlanner();
    cy.apiGet('/api/production/work-centers').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.be.an('array');
      expect(resp.body.data.length).to.be.greaterThan(0);
    });
  });

  it('Step 2: Create MPS entry', () => {
    cy.loginAsPlanner();
    cy.apiPost('/api/production/mps', {
      productId: 1,
      weekStart: '04/27/2026',
      quantity: 200,
    }).then((resp) => {
      expect(resp.status).to.eq(201);
      mpsId = resp.body.data.id;
    });
  });

  it('Step 3: Explode MPS into work orders', () => {
    cy.loginAsPlanner();
    cy.apiPost('/api/production/work-orders/explode', {
      mpsId: mpsId,
    }).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data.workOrdersCreated).to.be.greaterThan(0);
    });
  });

  it('Step 4: List work orders', () => {
    cy.loginAsPlanner();
    cy.apiGet('/api/production/work-orders?page=1&size=50').then((resp) => {
      expect(resp.status).to.eq(200);
      const wos = resp.body.data.list;
      expect(wos.length).to.be.greaterThan(0);
      workOrderId = wos[0].id;
    });
  });

  it('Step 5: Complete work order with reason code', () => {
    cy.loginAsPlanner();
    cy.apiPut(`/api/production/work-orders/${workOrderId}/complete`, {
      quantityCompleted: 95,
      quantityRework: 5,
      downtimeMinutes: 15,
      reasonCode: 'TOOL_WEAR',
    }).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data.status).to.eq('COMPLETED');
      expect(resp.body.data.reason_code).to.eq('TOOL_WEAR');
    });
  });

  it('Step 6: Check capacity loading', () => {
    cy.loginAsPlanner();
    cy.apiGet('/api/production/capacity').then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data).to.be.an('array');
      resp.body.data.forEach((wc) => {
        expect(wc).to.have.property('loadPercent');
        expect(wc).to.have.property('warning');
      });
    });
  });
});
