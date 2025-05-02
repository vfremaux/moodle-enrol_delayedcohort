<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrol_cohort', language 'en'.
 *
 * @package enrol_delayedcohort
 * @copyright 2010 Petr Skoda {@link http://skodak.org}
 * @copyright 2015 Valery Fremaux {@link http://www.edunao.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['delayedcohort:plan'] = 'Planifier les cohortes globalement';
$string['delayedcohort:config'] = 'Configurer les instances d\'inscription différée par cohorte';
$string['delayedcohort:unenrol'] = 'Désincrire les utilisateurs';

$string['addenrol'] = 'Configurer une inscription différée';
$string['addgroup'] = 'Ajouter au groupe';
$string['ajaxmore'] = 'Plus...';
$string['all'] = 'Tous les cours';
$string['assignrole'] = 'Assigner un rôle';
$string['bycohort'] = 'Par cohorte';
$string['bycourse'] = 'Par cours';
$string['cohortsearch'] = 'Chercher';
$string['cohortsplanner'] = 'Cohortes planifiées';
$string['delayed'] = 'différé';
$string['delayedcohortsplanner'] = 'Planing des cohortes différées';
$string['enddate'] = 'Date de fin d\'inscription';
$string['endsat'] = 'Finit le {$a}';
$string['enrolcohort'] = 'Inscrire une cohorte différée';
$string['enrolenddate'] = 'Date de clôture';
$string['enrolenddate_help'] = 'Si elle n\'est pas définie, les utilisateurs auront toujours accès au cours même après la date de fin administrative.';
$string['enrolstartdate'] = 'Date de mise en place';
$string['enrolstartdate_help'] = 'Si elle n\'est pas définie, le comportement sera équivalent au plugin d\'inscription par cohorte standard.';
$string['endsatcontinue'] = 'Finit le {$a} (sans désinscription)';
$string['enrolsync_task'] = 'Synchronisation des cohortes différées';
$string['event_delayedcohort_created'] = 'Instance de cohorte différée créée ';
$string['event_delayedcohort_deleted'] = 'Instance de cohorte différée supprimée ';
$string['event_delayedcohort_ended'] = 'Cohort différée désactivée ';
$string['event_delayedcohort_enrolled'] = 'Cohorte différée activée ';
$string['createfromcohort'] = 'Créer le groupe à partir de la cohorte';
$string['createfromform'] = 'Créer le groupe avec les infos ci-dessous';
$string['groupidnumber'] = "Numéro d'ientification du groupe";
$string['groupidnumber_help'] = "Veillez à l'unicité de l'identifiant de groupe";
$string['groupname'] = "Nom du groupe";
$string['grouppropmode'] = 'Mode de propagation du groupe';
$string['grouppropmode_help'] = '
Vous pouvez ici choisir de créer un nouveau groupe à partir des informations de cohorte ou 
à partir de données renseignées dans le formulaire, ou choisir un groupe existant pour y ajouter
les utilisateurs lors du démarrage de la synchronisation.
';
$string['instanceexists'] = 'La cohorte est déjà synchronisée sur ce rôle';
$string['legalstartdate'] = 'Date administrative de début de formation (pour les rapports)';
$string['legaldate'] = 'Dates administratives';
$string['legaldate_help'] = 'Cette date peut figurer sur des documents générés par le plugin Attestation de Formation. Par défaut ces dates prennent les valeurs de la période d\'inscription.';
$string['legalenddate'] = 'Date administrative de fin de formation (pour les rapports)';
$string['manageenrols'] = 'Gérer toutes les inscriptions';
$string['noprogrammablecohorts'] = 'Pas de cohortes programmables';
$string['notenabled'] = 'La méthode d\'inscription Cohortes Différées n\'est pas activée dans l\'administration centrale de Moodle.';
$string['notifyto'] = 'Notifier à';
$string['noend'] = 'Pas de fin';
$string['plannedcourses'] = 'Planning des cours';
$string['planner'] = 'Planning d\'activation des cohortes';
$string['pluginname'] = 'Synchronisation différée des cohortes';
$string['pluginname_desc'] = 'Ce plugin synchronise les inscriptions au cours sur les cohortes à une date programmable.';
$string['status'] = 'Actif';
$string['triggerdate'] = 'Date d\'exécution';
$string['unassigned'] = 'Non programmés';
$string['unenrolonend'] = 'Désinscrire à la date de fin';
$string['unplannedcourses'] = 'Cours non assignés';

$string['notifyto_help'] = 'Si non vide, enverra une notification aux adresses mail mentionnées (liste à virgules) lorsqu\'une activation de cohorte se déclenche';

$string['notifyaction_mail_object'] = '{$a->site}: Activation différée de la cohorte {$a->cohort} dans le cours {$a->shortname}';
$string['notifyaction_mail_raw'] = '
Activation différée de cohorte
-------------------------------
Les utilisateurs suivants appartenant à la cohorte {$a->cohort} ont été inscrits au cours [{$a->shortname}] {$a->fullname}:

{$a->userlist}

{$a->usermaillist}
';

$string['notifyaction_mail_html'] = '
<h2>Activation différée de cohorte</h2>
<p>Les utilisateurs suivants appartenant à la cohorte <b>{$a->cohort}</b> ont été inscrits au cours <b>[{$a->shortname}] {$a->fullname}</b>:</p>

<p>{$a->userlist}</p>

<p>{$a->usermaillist}</p>
';
