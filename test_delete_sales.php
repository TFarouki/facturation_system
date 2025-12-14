<?php
try {
    $id = 2;
    $receipt = \App\Models\SalesReceipt::find($id);
    if (!$receipt) {
        echo "Receipt $id not found.\n";
        exit;
    }

    echo "Found Receipt $id. Deleting...\n";

    // Simulate Controller logic
    $controller = new \App\Http\Controllers\Api\SalesController();
    $response = $controller->destroy($receipt);

    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response content: " . $response->getContent() . "\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
