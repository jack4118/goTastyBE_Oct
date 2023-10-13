<?php
$showIncrement = array(
    "GT0136-008-001,id:92",
    "GT0136-008-002,id:92",
    "GT0136-008-003,id:92",
    "GT0136-008-004,id:92",
    "GT0136-005-048,id:90",
    "GT0136-005-049,id:90",
    "GT0136-005-050,id:90",
    "GT0136-005-051,id:90",
    "GT0136-005-052,id:90",
    "GT0136-004-085,id:95",
    "GT0136-004-086,id:95",
    "GT0136-004-087,id:95",
    "GT0136-004-088,id:95",
    "GT0136-004-089,id:95"
);

// Custom sorting function using the 'id' from the showIncrement array
usort($showIncrement, function ($a, $b) {
    $idA = intval(explode(':', $a)[1]);
    $idB = intval(explode(':', $b)[1]);
    
    return $idA - $idB;
});

// Output the sorted showIncrement array
print_r($showIncrement);
?>
