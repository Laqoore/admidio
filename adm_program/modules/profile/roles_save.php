<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id: Funktionen der uebergebenen ID aendern
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");


// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!isModerator() && !isGroupLeader() && !$g_current_user->editUser())
{
    $g_message->show("norights");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]) && is_numeric($_GET["user_id"]) == false)
{
    $g_message->show("invalid");
}

if(isModerator())
{
    // Alle Rollen der Gruppierung auflisten
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_ROLES. "
                WHERE rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                ORDER BY rol_name";
}
elseif(isGroupLeader())
{
    // Alle Rollen auflisten, bei denen das Mitglied Leiter ist
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id  = $g_current_user->id
                  AND mem_valid  = 1
                  AND mem_leader = 1
                  AND rol_id     = mem_rol_id
                  AND rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                  AND rol_locked     = 0
                ORDER BY rol_name";
}
elseif($g_current_user->editUser())
{
    // Alle Rollen auflisten, die keinen Moderatorenstatus haben
    $sql    = "SELECT rol_id, rol_name, rol_max_members
                 FROM ". TBL_ROLES. "
                WHERE rol_org_shortname = '$g_organization'
                  AND rol_valid        = 1
                  AND rol_moderation = 0
                  AND rol_locked     = 0
                ORDER BY rol_name";
}
$result_rolle = mysql_query($sql, $g_adm_con);
db_error($result_rolle);

$count_assigned = 0;
$i     = 0;
$value = reset($_POST);
$key   = key($_POST);
$parentRoles = array();


// Ergebnisse durchlaufen und kontrollieren ob maximale Teilnehmerzahl ueberschritten wuerde
while($row = mysql_fetch_object($result_rolle))
{
    if($row->rol_max_members > 0)
    {
        // erst einmal schauen, ob der Benutzer dieser Rolle bereits zugeordnet ist
        $sql    =   "SELECT COUNT(*)
                       FROM ". TBL_MEMBERS. "
                      WHERE mem_rol_id = $row->rol_id
                        AND mem_usr_id = {0}
                        AND mem_leader = 0
                        AND mem_valid  = 1";
        $sql    = prepareSQL($sql, array($_GET['user_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $row_usr = mysql_fetch_array($result);

        if($row_usr[0] == 0)
        {
            // Benutzer ist der Rolle noch nicht zugeordnet, dann schauen, ob die Anzahl ueberschritten wird
            $sql    =   "SELECT COUNT(*)
                           FROM ". TBL_MEMBERS. "
                          WHERE mem_rol_id = $row->rol_id
                            AND mem_leader = 0
                            AND mem_valid  = 1";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);

            $row_members = mysql_fetch_array($result);

            //Bedingungen fuer Abbruch und Abbruch
            if($row_members[0] >= $row->rol_max_members
            && $_POST["leader-$i"] == false
            && $_POST["role-$i"]   == true)
            {
                $g_message->show("max_members_profile", utf8_encode($row->rol_name));
            }
        }
    }
    $i++;
}

//Dateizeiger auf erstes Element zurueck setzen
if(mysql_num_rows($result_rolle)>0)
{
    mysql_data_seek($result_rolle, 0);
}
$i     = 0;

// Ergebnisse durchlaufen und Datenbankupdate durchfuehren
while($row = mysql_fetch_object($result_rolle))
{
    // der Webmaster-Rolle duerfen nur Webmaster neue Mitglieder zuweisen
    if($row->rol_name != 'Webmaster' || hasRole('Webmaster'))
    {
        if($key == "role-$i")
        {
            $function = 1;
            $value    = next($_POST);
            $key      = key($_POST);
        }
        else
        {
            $function = 0;
        }

        if($key == "leader-$i")
        {
            $leiter   = 1;
            $value    = next($_POST);
            $key      = key($_POST);
        }
        else
        {
            $leiter   = 0;
        }

        $sql    = "SELECT * FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                    WHERE mem_rol_id = $row->rol_id
                      AND mem_usr_id = {0}
                      AND mem_rol_id = rol_id ";
        $sql    = prepareSQL($sql, array($_GET['user_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $user_found = mysql_num_rows($result);

        if($user_found > 0)
        {
            // neue Mitgliederdaten zurueckschreiben
            if($function == 1)
            {
                $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 1
                                                   , mem_end    = NULL
                                                   , mem_leader = $leiter
                            WHERE mem_rol_id = $row->rol_id
                              AND mem_usr_id = {0}";
                $count_assigned++;
            }
            else
            {
                $sql = "UPDATE ". TBL_MEMBERS. " SET mem_valid  = 0
                                                   , mem_end    = NOW()
                                                   , mem_leader = $leiter
                            WHERE mem_rol_id = $row->rol_id
                              AND mem_usr_id = {0}";
            }
        }
        else
        {
            // neue Mitgliederdaten einfuegen, aber nur, wenn auch ein Haeckchen da ist
            if($function == 1)
            {
                $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader)
                          VALUES ($row->rol_id, {0}, NOW(),NULL, 1, $leiter) ";
                $count_assigned++;
            }
        }


        // Update aufueren
        $sql    = prepareSQL($sql, array($_GET['user_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        // find the parent roles
        if($function == 1 && $user_found < 1)
        {
            //$roleDepSrc = new RoleDependency($g_adm_con);
            $tmpRoles = RoleDependency::getParentRoles($g_adm_con,$row->rol_id);

            foreach($tmpRoles as $tmpRole)
            {
                if(!in_array($tmpRole,$parentRoles))
                $parentRoles[] = $tmpRole;
            }

        }

    }

   $i++;
}

$_SESSION['navigation']->deleteLastUrl();

// falls Rollen dem eingeloggten User neu zugewiesen wurden, 
// dann muessen die Rechte in den Session-Variablen neu eingelesen werden
if($g_current_user->id != $_GET['user_id'])
{
    $g_current_user->clearRights();
    $_SESSION['g_current_user'] = $g_current_user;
}

foreach($parentRoles as $actRole)
{
    $sql = "INSERT INTO ". TBL_MEMBERS. " (mem_rol_id, mem_usr_id, mem_begin,mem_end, mem_valid, mem_leader)
              VALUES ($actRole, {0}, NOW(), NULL, 1, $leiter) ";
    $sql    = prepareSQL($sql, array($_GET['user_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
}

if($_GET['new_user'] == 1 && $count_assigned == 0)
{
    // Neuem User wurden keine Rollen zugewiesen
    $g_message->show("norolle");
}

// zur Ausgangsseite zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show("save");