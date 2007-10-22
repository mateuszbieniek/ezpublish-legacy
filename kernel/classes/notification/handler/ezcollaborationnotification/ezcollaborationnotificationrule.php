<?php
//
// Definition of eZCollaborationNotificationRule class
//
// Created on: <09-Jul-2003 16:36:55 amos>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ publish
// SOFTWARE RELEASE: 3.9.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2006 eZ systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file ezcollaborationnotificationrule.php
*/

/*!
  \class eZCollaborationNotificationRule ezcollaborationnotificationrule.php
  \brief The class eZCollaborationNotificationRule does

*/
include_once( 'kernel/classes/ezcontentobjecttreenode.php' );

class eZCollaborationNotificationRule extends eZPersistentObject
{
    /*!
     Constructor
    */
    function eZCollaborationNotificationRule( $row )
    {
        $this->eZPersistentObject( $row );
    }

    function definition()
    {
        return array( "fields" => array( "id" => array( 'name' => 'ID',
                                                        'datatype' => 'integer',
                                                        'default' => 0,
                                                        'required' => true ),
                                         "user_id" => array( 'name' => "UserID",
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => true,
                                                             'foreign_class' => 'eZUser',
                                                             'foreign_attribute' => 'contentobject_id',
                                                             'multiplicity' => '1..*' ),
                                         "collab_identifier" => array( 'name' => "CollaborationIdentifier",
                                                               'datatype' => 'string',
                                                               'default' => '',
                                                               'required' => true ) ),
                      "keys" => array( "id" ),
                      "function_attributes" => array( 'user' => 'user' ),
                      "increment_key" => "id",
                      "sort" => array( "id" => "asc" ),
                      "class_name" => "eZCollaborationNotificationRule",
                      "name" => "ezcollab_notification_rule" );
    }

    function &user()
    {
        $user = eZUser::fetch( $this->attribute( 'user_id' ) );
        return $user;
    }

    function create( $collaborationIdentifier, $userID = false )
    {
        if ( !$userID )
            $userID = eZUser::currentUserID();
        $rule = new eZCollaborationNotificationRule( array( 'user_id' => $userID,
                                                            'collab_identifier' => $collaborationIdentifier ) );
        return $rule;
    }

    function fetchList( $userID = false, $asObject = true )
    {
        if ( !$userID )
            $userID = eZUser::currentUserID();
        return eZPersistentObject::fetchObjectList( eZCollaborationNotificationRule::definition(),
                                                    null, array( 'user_id' => $userID ),
                                                    null, null, $asObject );
    }

    function &fetchItemTypeList( $collaborationIdentifier, $userIDList, $asObject = true )
    {
        if ( is_array( $collaborationIdentifier ) )
            $collaborationIdentifier = array( $collaborationIdentifier );
        $objectList = eZPersistentObject::fetchObjectList( eZCollaborationNotificationRule::definition(),
                                                    null, array( 'user_id' => array( $userIDList ),
                                                                 'collab_identifier' => $collaborationIdentifier ),
                                                    null, null, $asObject );
        return $objectList;
    }

//     function &fetchUserList( $nodeIDList )
//     {
//         $rules = eZPersistentObject::fetchObjectList( eZCollaborationNotificationRule::definition(),
//                                                       array(), array( 'node_id' => array( $nodeIDList ) ),
//                                                       array( 'address' => 'asc' , 'use_digest' => 'desc'  ),null,
//                                                       false, false, array( array( 'operation' => 'distinct address,use_digest' ) )  );
//         return $rules;
//     }

//     function node()
//     {
//         if ( $this->Node == null )
//         {
//             $this->Node = eZContentObjectTreeNode::fetch( $this->attribute( 'node_id' ) );
//         }
//         return $this->Node;
//     }

    function removeByIdentifier( $collaborationIdentifier, $userID = false )
    {
        if ( !$userID )
            $userID = eZUser::currentUserID();
        eZPersistentObject::removeObject( eZCollaborationNotificationRule::definition(),
                                          array( 'collab_identifier' => $collaborationIdentifier,
                                                 'user_id' => $userID ) );
    }

//     function removeByNodeAndAddress( $address, $nodeID )
//     {
//         eZPersistentObject::removeObject( eZCollaborationNotificationRule::definition(), array( 'address' => $address,
//                                                                                                 'node_id' => $nodeID ) );
//     }
//     var $Node = null;

    /*!
     \static

     Remove notifications by user id

     \param userID
    */
    function removeByUserID( $userID )
    {
        eZPersistentObject::removeObject( eZCollaborationNotificationRule::definition(), array( 'user_id' => $userID ) );
    }

    /*!
     \static
     Removes all notification rules for all collaboration items for all users.
    */
    function cleanup()
    {
        $db =& eZDB::instance();
        $db->query( "DELETE FROM ezcollab_notification_rule" );
    }
}

?>
