<?php
namespace RefactoredGroup\AutoFflCore\Model;

/** Chooses a checkout flow without treating an unknown ammunition state as unrestricted. */
class CheckoutRouting
{
    public function decide(array $analysis)
    {
        if (!$analysis['unresolved']) {
            return !empty($analysis['required']) && !$analysis['allRequired']
                ? 'multishipping' : 'standard';
        }
        if (!empty($analysis['firearms']) && !empty($analysis['ordinary'])) {
            return 'multishipping';
        }
        $ammoCount = 0;
        foreach ($analysis['ammo'] as $entry) {
            $ammoCount += count($entry['items']);
        }
        return $ammoCount === $analysis['physicalCount'] ? 'standard' : 'state';
    }
}
