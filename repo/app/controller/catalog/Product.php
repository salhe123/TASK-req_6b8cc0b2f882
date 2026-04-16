<?php
declare(strict_types=1);

namespace app\controller\catalog;

use app\BaseController;
use app\model\Product as ProductModel;
use app\service\CatalogService;
use app\service\AuditService;
use think\exception\ValidateException;

class Product extends BaseController
{
    /**
     * Catalog object-level ownership gate.
     *
     * A PRODUCT_SPECIALIST may only operate on a product they created —
     * the prompt-defined separation of duties means one specialist's drafts
     * must not be editable or submittable by another specialist. Reviewers,
     * moderators, planners and SYSTEM_ADMIN retain broader read access and
     * bypass this gate because they have distinct role-level guards.
     */
    private function assertOwnsProduct(ProductModel $product): ?\think\Response
    {
        $user = session('user');
        if (!$user) {
            return json_error('Unauthorized', 401);
        }
        if ($user['role'] !== 'PRODUCT_SPECIALIST') {
            return null;
        }
        if ((int) $product->created_by !== (int) $user['id']) {
            return json_error('You can only modify your own catalog drafts', 403);
        }
        return null;
    }

    public function index()
    {
        $user     = session('user');
        $category = $this->request->get('category', '');
        $status   = $this->request->get('status', '');
        $keyword  = $this->request->get('keyword', '');
        $page     = (int) $this->request->get('page', 1);
        $size     = (int) $this->request->get('size', 20);

        $query = ProductModel::order('created_at', 'desc');

        if ($category) {
            $query->where('category', $category);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        // A REVIEWER must only ever see products they have been assigned to
        // review. Without this restriction the list endpoint would let a
        // reviewer enumerate the full catalog and defeat blind-review
        // confidentiality entirely (separate from the single-read guard).
        if ($user && $user['role'] === 'REVIEWER') {
            $assignedProductIds = \think\facade\Db::name('review_assignments')
                ->where('reviewer_id', (int) $user['id'])
                ->column('product_id');
            if (empty($assignedProductIds)) {
                return json_success(['list' => [], 'total' => 0, 'page' => $page, 'size' => $size]);
            }
            $query->whereIn('id', $assignedProductIds);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        // Apply blind masking to each row where the reviewer is assigned in
        // blind mode. Index by (product_id => assignment) once so we don't
        // hit the DB per row.
        if ($user && $user['role'] === 'REVIEWER') {
            $assignments = \think\facade\Db::name('review_assignments')
                ->where('reviewer_id', (int) $user['id'])
                ->field('product_id, blind')
                ->select()
                ->toArray();
            $blindById = [];
            foreach ($assignments as $a) {
                $blindById[(int) $a['product_id']] = !empty($a['blind']);
            }
            foreach ($list as &$row) {
                if (!empty($blindById[(int) $row['id']])) {
                    foreach (['vendor_name', 'submitted_by', 'created_by'] as $k) {
                        if (array_key_exists($k, $row)) {
                            $row[$k] = null;
                        }
                    }
                    $row['blind_masked'] = true;
                }
            }
            unset($row);
        }

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['name']) || empty($data['category'])) {
            return json_error('name and category are required', 400);
        }

        try {
            $product = CatalogService::create($data, $user['id']);
            return json_success($product->toArray(), 'Product created', 201);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function read($id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return json_error('Product not found', 404);
        }

        $user = session('user');
        $payload = $product->toArray();

        // Blind-review confidentiality: a REVIEWER reading a product they are
        // blind-assigned to must NOT see vendor / submitter fields. If the
        // reviewer has no assignment at all, deny the read outright — they
        // have no business reading arbitrary catalog rows.
        if ($user && $user['role'] === 'REVIEWER') {
            $assignment = \think\facade\Db::name('review_assignments')
                ->where('product_id', (int) $id)
                ->where('reviewer_id', (int) $user['id'])
                ->find();
            if (!$assignment) {
                return json_error('No review assignment for this product', 403);
            }
            if (!empty($assignment['blind'])) {
                foreach (['vendor_name', 'submitted_by', 'created_by'] as $k) {
                    if (array_key_exists($k, $payload)) {
                        $payload[$k] = null;
                    }
                }
                $payload['blind_masked'] = true;
            }
        }

        return json_success($payload);
    }

    public function update($id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return json_error('Product not found', 404);
        }
        if ($deny = $this->assertOwnsProduct($product)) {
            return $deny;
        }

        if ($product->status !== 'DRAFT') {
            return json_error('Only DRAFT products can be edited', 409);
        }

        $data   = json_decode($this->request->getInput(), true) ?: [];
        $before = $product->toArray();

        if (isset($data['name'])) {
            $product->name = $data['name'];
        }
        if (isset($data['specs'])) {
            $product->specs = json_encode($data['specs']);
        }

        $product->updated_at = date('Y-m-d H:i:s');
        $product->save();

        AuditService::log('PRODUCT_UPDATED', 'product', (int) $id, $before, $product->toArray());

        return json_success($product->toArray());
    }

    public function submit($id)
    {
        $product = ProductModel::find($id);
        if (!$product) {
            return json_error('Product not found', 404);
        }
        if ($deny = $this->assertOwnsProduct($product)) {
            return $deny;
        }

        $user = session('user');
        try {
            $result = CatalogService::submit((int) $id, $user['id']);
            return json_success($result);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 409);
        }
    }

    public function duplicates()
    {
        $pairs = CatalogService::findDuplicates();
        return json_success($pairs);
    }
}
