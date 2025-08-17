<?php
/**
 * Test script to demonstrate product deletion functionality
 * 
 * This script shows how the product deletion hook works.
 * Run with: wp eval-file test-product-deletion.php
 */

// Ensure this is run in WordPress context
if (!defined('ABSPATH')) {
    echo "This script must be run in WordPress context using WP-CLI\n";
    echo "Usage: wp eval-file test-product-deletion.php\n";
    exit(1);
}

// Test data
const TEST_PRODUCT_ID = 999999; // Use a high ID that won't conflict
const TEST_CUSTOMER_ID = 1;
const TEST_REQUESTER_ID = 1;

echo "=== WRC Product Deletion Hook Test ===\n\n";

try {
    // 1. Create test lease requests
    echo "1. Creating test lease requests...\n";
    
    $request1 = new \WRC\Domain\LeaseRequest(
        null,
        TEST_PRODUCT_ID,
        null,
        TEST_REQUESTER_ID,
        '2024-02-01T10:00',
        '2024-02-05T16:00',
        2,
        'pending',
        'Test request 1'
    );
    
    $request2 = new \WRC\Domain\LeaseRequest(
        null,
        TEST_PRODUCT_ID,
        null,
        TEST_REQUESTER_ID,
        '2024-03-01T10:00',
        '2024-03-05T16:00',
        1,
        'approved',
        'Test request 2'
    );
    
    $requestId1 = \WRC\Domain\LeaseRequest::create($request1);
    $requestId2 = \WRC\Domain\LeaseRequest::create($request2);
    
    echo "   Created lease request #{$requestId1}\n";
    echo "   Created lease request #{$requestId2}\n";

    // 2. Create test leases
    echo "\n2. Creating test leases...\n";
    
    $lease1 = new \WRC\Domain\Lease(
        null,
        TEST_PRODUCT_ID,
        null,
        null,
        null,
        TEST_CUSTOMER_ID,
        $requestId2, // Link to approved request
        '2024-03-01T10:00',
        '2024-03-05T16:00',
        1,
        'active'
    );
    
    $leaseId1 = \WRC\Domain\Lease::create($lease1);
    echo "   Created lease #{$leaseId1}\n";

    // 3. Verify data exists
    echo "\n3. Verifying data exists...\n";
    
    $requests = \WRC\Domain\LeaseRequest::listAsArray(['product_id' => TEST_PRODUCT_ID], 1, 10);
    $leases = \WRC\Domain\Lease::listAsArray(['product_id' => TEST_PRODUCT_ID]);
    
    echo "   Found {$requests['total']} lease requests for product " . TEST_PRODUCT_ID . "\n";
    echo "   Found " . count($leases) . " leases for product " . TEST_PRODUCT_ID . "\n";

    // 4. Simulate product deletion by calling our cleanup method directly
    echo "\n4. Simulating product deletion...\n";
    
    $deletedRequests = \WRC\Domain\LeaseRequest::deleteByProductId(TEST_PRODUCT_ID);
    $deletedLeases = \WRC\Domain\Lease::deleteByProductId(TEST_PRODUCT_ID);
    
    echo "   Deleted {$deletedRequests} lease requests\n";
    echo "   Deleted {$deletedLeases} leases\n";

    // 5. Verify cleanup
    echo "\n5. Verifying cleanup...\n";
    
    $requestsAfter = \WRC\Domain\LeaseRequest::listAsArray(['product_id' => TEST_PRODUCT_ID], 1, 10);
    $leasesAfter = \WRC\Domain\Lease::listAsArray(['product_id' => TEST_PRODUCT_ID]);
    
    echo "   Remaining lease requests: {$requestsAfter['total']}\n";
    echo "   Remaining leases: " . count($leasesAfter) . "\n";

    echo "\n✅ Test completed successfully!\n";
    echo "\nNote: In real usage, this cleanup happens automatically when a WooCommerce product is deleted.\n";
    echo "The hooks registered are:\n";
    echo "  - woocommerce_before_delete_product (WooCommerce specific)\n";
    echo "  - before_delete_post (WordPress fallback for product post type)\n";

} catch (Exception $e) {
    echo "\n❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
