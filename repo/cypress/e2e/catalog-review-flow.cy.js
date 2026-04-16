describe('Catalog → Submit → Moderate → Review → Publish E2E', () => {
  let productId;
  let scorecardId;
  let assignmentId;
  let submissionId;

  it('Step 1: Create product draft', () => {
    cy.loginAsModerator();
    cy.apiPost('/api/catalog/products', {
      name: 'E2E Test CPU ' + Date.now(),
      category: 'CPU',
      specs: {
        clockSpeed: '3500 MHz',
        cores: 16,
        threads: 32,
        socket: 'AM5',
        tdp: '170 W',
        cache: '64 MB',
        architecture: 'Zen 4',
      },
    }).then((resp) => {
      expect(resp.status).to.eq(201);
      expect(resp.body.data.status).to.eq('DRAFT');
      productId = resp.body.data.id;
    });
  });

  it('Step 2: Submit product for scoring', () => {
    cy.loginAsModerator();
    cy.apiPost(`/api/catalog/products/${productId}/submit`, {}).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data.status).to.eq('SUBMITTED');
      expect(resp.body.data.completenessScore).to.eq(1);
    });
  });

  it('Step 3: Moderator approves product', () => {
    cy.loginAsModerator();
    cy.apiPost('/api/moderation/bulk-action', {
      ids: [productId],
      action: 'APPROVE',
    }).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data.processed).to.eq(1);
    });
  });

  it('Step 4: Admin creates scorecard', () => {
    cy.loginAsAdmin();
    cy.apiPost('/api/reviews/scorecards', {
      name: 'E2E Scorecard ' + Date.now(),
      dimensions: [
        { name: 'Build Quality', weight: 30 },
        { name: 'Performance', weight: 25 },
        { name: 'Value', weight: 20 },
        { name: 'Documentation', weight: 25 },
      ],
    }).then((resp) => {
      expect(resp.status).to.eq(201);
      scorecardId = resp.body.data.id;
    });
  });

  it('Step 5: Auto-assign reviewer', () => {
    cy.loginAsAdmin();
    cy.apiPost('/api/reviews/assignments/auto', {
      productId: productId,
      blind: true,
    }).then((resp) => {
      expect(resp.status).to.eq(201);
      expect(resp.body.data.status).to.eq('ASSIGNED');
      assignmentId = resp.body.data.id;
    });
  });

  it('Step 6: Submit review with narratives', () => {
    cy.loginAsAdmin();
    // Get scorecard dimensions
    cy.apiGet('/api/reviews/scorecards').then((resp) => {
      const scorecard = resp.body.data.find((s) => s.id === scorecardId);
      const ratings = scorecard.dimensions.map((d) => ({
        dimensionId: d.id,
        score: 4,
        narrative: 'E2E test review for ' + d.name,
      }));

      cy.apiPost('/api/reviews/submissions', {
        assignmentId: assignmentId,
        scorecardId: scorecardId,
        ratings: ratings,
      }).then((subResp) => {
        expect(subResp.status).to.eq(200);
        expect(subResp.body.data.status).to.eq('SUBMITTED');
        submissionId = subResp.body.data.id;
      });
    });
  });

  it('Step 7: Publish review', () => {
    cy.loginAsAdmin();
    cy.apiPost(`/api/reviews/submissions/${submissionId}/publish`, {}).then((resp) => {
      expect(resp.status).to.eq(200);
      expect(resp.body.data.status).to.eq('PUBLISHED');
    });
  });
});
