<?php

return array(
    'Permitions'=> array(
        'Article'=> 'VAMD',
        'Uploads'=> 'VAD',
        'Comments'=> 'VMDP',
        'ClearCache'=> '*',
    ),
    'Roles'=> array(
        'Administrator'=> 'Has access to all backend tools.',
        'Editor'=> 'Can write and manage articles, upload files and manage comments.',
        'CommunityMgr'=> 'Can manage comments.',
    ),
    'RoleInheritances'=> array(
        'Administrator'=> array('Editor','CommunityMgr'),
    ),
    'Permition-Role'=> array(
        'Article'=> array(
            'Administrator'=> '*',
            'Editor'=> 'VAM',
            'CommunityMgr'=> 'V',
        ),
        'Uploads'=> array(
            'Administrator'=> '*',
            'Editor'=> 'VA',
        ),
        'Comments'=> array(
            'CommunityMgr'=> '*', // admin should inherit this
        ),
        'ClearCache'=> array(
            'Administrator'=> '*',
        ),
    ),
    'User-Role'=> array(
        1=> array('Administrator'),
        2=> array('Editor'),
        3=> array('CommunityMgr'),
        4=> array(),
        5=> array('Editor','CommunityMgr'),
    ),
);

?>