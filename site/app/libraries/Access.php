<?php

namespace app\libraries;

use app\models\Gradeable;
use app\models\gradeable\Component;
use app\models\gradeable\GradedGradeable;
use app\models\GradeableComponent;
use app\models\GradingSection;
use app\models\User;
use InvalidArgumentException;

class Access {
    // Access control options

    /** Allow Instructors to do this */
    const ALLOW_INSTRUCTOR              = 1 << 0;
    /** Allow full access graders to do this */
    const ALLOW_FULL_ACCESS_GRADER      = 1 << 1;
    /** Allow limited access graders to do this */
    const ALLOW_LIMITED_ACCESS_GRADER   = 1 << 2;
    /** Allow students to do this */
    const ALLOW_STUDENT                 = 1 << 3;
    /** Allow logged out users to do this */
    const ALLOW_LOGGED_OUT              = 1 << 4;
    /**
     * Check that the current user is at or above the minimum grading group required for a gradeable
     * If the gradeable has peer grading, this will also accept for students
     */
    const CHECK_GRADEABLE_MIN_GROUP     = 1 << 5 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check that a given user is in the current user's grading section for a gradeable
     * Only applies to limited access graders
     */
    const CHECK_GRADING_SECTION_GRADER  = 1 << 6 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check that a given user is in the current user's peer grading assignment for a gradeable
     * Only applies to students
     */
    const CHECK_PEER_ASSIGNMENT_STUDENT = 1 << 7 | self::REQUIRE_ARG_GRADEABLE;
    /** Require that the given gradeable have an active version / submission */
    const CHECK_HAS_SUBMISSION          = 1 << 8 | self::REQUIRE_ARG_GRADEABLE;
    /** Check that a valid CSRF token was passed in the request */
    const CHECK_CSRF                    = 1 << 9;
    /** Allow access if the gradeable is our own, even if sections are checked */
    const ALLOW_SELF_GRADEABLE          = 1 << 10 | self::REQUIRE_ARG_GRADEABLE;
    /**
     * Check if the given component allows peer grading
     * Only applies to students
     */
    const CHECK_COMPONENT_PEER_STUDENT  = 1 << 11 | self::REQUIRE_ARG_COMPONENT;

    /** If the current set of flags requires the "gradeable" argument */
    const REQUIRE_ARG_GRADEABLE         = 1 << 24;
    /** If the current set of flags requires the "gradeable" argument */
    const REQUIRE_ARG_COMPONENT         = 1 << 25;

    // Broader user group access cases since generally actions are "minimum this group"

    const ALLOW_MIN_STUDENT               = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER | self::ALLOW_LIMITED_ACCESS_GRADER | self::ALLOW_STUDENT;
    const ALLOW_MIN_LIMITED_ACCESS_GRADER = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER | self::ALLOW_LIMITED_ACCESS_GRADER;
    const ALLOW_MIN_FULL_ACCESS_GRADER    = self::ALLOW_INSTRUCTOR | self::ALLOW_FULL_ACCESS_GRADER;
    const ALLOW_MIN_INSTRUCTOR            = self::ALLOW_INSTRUCTOR;

    /**
     * @var Core
     */
    private $core;
    private $permissions = [];

    public function __construct(Core $core) {
        $this->core = $core;

        $this->permissions["grading.status"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.status.full"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.details"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.details.show_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.details.show_all_no_sections"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.details.show_empty_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.grade"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.grade.if_no_sections_exist"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.save_one_component"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION | self::CHECK_COMPONENT_PEER_STUDENT;
        $this->permissions["grading.save_general_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_HAS_SUBMISSION;
        $this->permissions["grading.get_mark_data"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::CHECK_COMPONENT_PEER_STUDENT;
        $this->permissions["grading.get_gradeable_comment"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT;
        $this->permissions["grading.add_one_new_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.delete_one_mark"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
        $this->permissions["grading.get_marked_users"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP;
        $this->permissions["grading.get_marked_users.full_stats"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.show_edit_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.import_teams"] = self::ALLOW_MIN_INSTRUCTOR | self::CHECK_CSRF;
        $this->permissions["grading.export_teams"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.submit_team_form"] = self::ALLOW_MIN_INSTRUCTOR;
        $this->permissions["grading.verify_grader"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;
        $this->permissions["grading.verify_all"] = self::ALLOW_MIN_FULL_ACCESS_GRADER;

        $this->permissions["autograding.load_checks"] = self::ALLOW_MIN_STUDENT | self::CHECK_GRADING_SECTION_GRADER | self::CHECK_PEER_ASSIGNMENT_STUDENT | self::ALLOW_SELF_GRADEABLE;
        $this->permissions["autograding.show_hidden_cases"] = self::ALLOW_MIN_LIMITED_ACCESS_GRADER | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADEABLE_MIN_GROUP | self::CHECK_GRADING_SECTION_GRADER;
    }

    /**
     * Check if the currently logged in user is allowed to do an action
     * @param string $action Name of the action (see Access::$permissions)
     * @param array $args Any extra arguments that are required to check permissions
     * @return bool True if they are allowed to do that action
     */
    public function canI(string $action, $args = []) {
        if (!array_key_exists($action, $this->permissions)) {
            throw new InvalidArgumentException("Unknown action '$action'");
        }
        $checks = $this->permissions[$action];

        //Some things may be available when there is no user
        $user = $this->core->getUser();
        if ($user === null) {
            if (!($checks & self::ALLOW_LOGGED_OUT)) {
                return false;
            }
            $group = User::GROUP_NONE;
        } else {
            $group = $user->getGroup();
        }

        //Check user group first
        if ($group === User::GROUP_STUDENT && !self::checkBits($checks, self::ALLOW_STUDENT)) {
            return false;
        } else if ($group === User::GROUP_LIMITED_ACCESS_GRADER && !self::checkBits($checks, self::ALLOW_LIMITED_ACCESS_GRADER)) {
            return false;
        } else if ($group === User::GROUP_FULL_ACCESS_GRADER && !self::checkBits($checks, self::ALLOW_FULL_ACCESS_GRADER)) {
            return false;
        } else if ($group === User::GROUP_INSTRUCTOR && !self::checkBits($checks, self::ALLOW_INSTRUCTOR)) {
            return false;
        }

        if (self::checkBits($checks, self::CHECK_CSRF)) {
            if ($this->core->checkCsrfToken()) {
                return false;
            }
        }

        if (self::checkBits($checks, self::REQUIRE_ARG_GRADEABLE)) {
            /* @var Gradeable|\app\models\gradeable\Gradeable|null $gradeable */
            $gradeable = $this->requireArg($args, "gradeable");
            if ($gradeable === null) {
                return false;
            }

            // TODO: Remove once new model is fully integrated
            if ($gradeable instanceof GradedGradeable) {
                $graded_gradeable = $gradeable;
                $gradeable = $gradeable->getGradeable();
            }

            if (self::checkBits($checks, self::CHECK_GRADEABLE_MIN_GROUP)) {
                // TODO: Remove once new model is fully integrated
                $min_grading_group = $gradeable instanceof Gradeable
                    ? $gradeable->getMinimumGradingGroup()
                    : $gradeable->getMinGradingGroup();
                //Make sure they meet the minimum requirements
                if (!$this->checkGroupPrivilege($group, $min_grading_group)) {

                    if (
                        //Full access graders are allowed to view submissions if there is no manual grading
                        !($group === User::GROUP_FULL_ACCESS_GRADER && !$gradeable->isTaGrading())
                        &&
                        //Students are allowed to see this if its a peer graded assignment
                        !($group === User::GROUP_STUDENT && $gradeable->getPeerGrading())
                    ) {

                        //Otherwise, you're not allowed
                        return false;
                    }
                }
            }

            if (self::checkBits($checks, self::CHECK_HAS_SUBMISSION)) {
                // TODO: Remove once new model is fully integrated
                $active_version = $gradeable instanceof Gradeable
                    ? $gradeable->getActiveVersion()
                    : $graded_gradeable->getAutoGradedGradeable()->getActiveVersion();
                if ($active_version <= 0) {
                    return false;
                }
            }

            if (self::checkBits($checks, self::CHECK_GRADING_SECTION_GRADER) && $group === User::GROUP_LIMITED_ACCESS_GRADER) {
                // TODO: Remove once new model is fully integrated
                $condition = $gradeable instanceof Gradeable
                    ? !$this->checkGradingSection($gradeable)
                    : !$this->checkGradingSectionNewModel($graded_gradeable);
                //Check their grading section
                if ($condition) {
                    return false;
                }
            }

            if (self::checkBits($checks, self::CHECK_PEER_ASSIGNMENT_STUDENT) && $group === User::GROUP_STUDENT) {
                // TODO: Remove once new model is fully integrated
                $user_id = $gradeable instanceof Gradeable
                    ? $gradeable->getUser()->getId()
                    : $graded_gradeable->getSubmitter()->getUser()->getId();

                //If they're allowed to view their own
                if (!($user_id === $user->getId() && self::checkBits($checks, self::ALLOW_SELF_GRADEABLE))) {
                    // TODO: Remove once new model is fully integrated
                    $condition = $gradeable instanceof Gradeable
                        ? !$this->checkPeerAssignment($gradeable)
                        : !$this->checkPeerAssignmentNewModel($graded_gradeable);

                    //Check their peer assignment
                    if ($condition) {
                        return false;
                    }
                }
            }
        }

        if (self::checkBits($checks, self::REQUIRE_ARG_COMPONENT)) {
            /* @var GradeableComponent|Component|null $component */
            $component = $this->requireArg($args, "component");
            if ($component === null) {
                return false;
            }

            if (self::checkBits($checks, self::CHECK_COMPONENT_PEER_STUDENT) && $group === User::GROUP_STUDENT) {
                // TODO: Remove once new model is fully integrated
                $condition = $component instanceof GradeableComponent
                    ? !$component->getIsPeer()
                    : !$component->isPeer();

                //Make sure a component allows students to access it via peer grading
                if ($condition) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Require that a named key is in the list of args. Throws otherwise
     * @param array $args List of args
     * @param string $name Name of required arg
     * @return mixed Arg value
     */
    private function requireArg(array $args, string $name) {
        if (!array_key_exists($name, $args)) {
            throw new InvalidArgumentException("Missing argument '$name'");
        }
        return $args[$name];
    }

    /**
     * Check if a bit set matches a given bit mask
     * @param int $bits Bit set (list of flags)
     * @param int $test Bit mask (flags to check for)
     * @return bool If matches
     */
    private function checkBits(int $bits, int $test) {
        return ($bits & $test) === $test;
    }

    /**
     * Check if a limited access grader has a user in their section
     * @param Gradeable $gradeable
     * @return bool If they are
     */
    public function checkGradingSection(Gradeable $gradeable) {
        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if ($gradeable->getGradeStartDate() <= $now) {
            //Check to see if the requested user is assigned to this grader.
            $sections = $gradeable->getGradingSectionsForUser($this->core->getUser());


            foreach ($sections as $section) {
                /** @var GradingSection $section */
                if ($gradeable->isTeamAssignment()) {
                    if ($section->containsTeam($gradeable->getTeam())) {
                        return true;
                    }
                } else {
                    if ($section->containsUser($gradeable->getUser())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a limited access grader has a user in their section
     * @param GradedGradeable $graded_gradeable
     * @return bool If they are
     */
    public function checkGradingSectionNewModel(GradedGradeable $graded_gradeable) {
        $gradeable = $graded_gradeable->getGradeable();
        $now = new \DateTime("now", $this->core->getConfig()->getTimezone());

        //If a user is a limited access grader, and the gradeable is being graded, and the
        // gradeable can be viewed by limited access graders.
        if ($gradeable->getGradeStartDate() <= $now) {
            //Check to see if the requested user is assigned to this grader.
            $sections = $gradeable->getGradingSectionsForUser($this->core->getUser());


            foreach ($sections as $section) {
                /** @var GradingSection $section */
                if ($gradeable->isTeamAssignment()) {
                    if ($section->containsTeam($graded_gradeable->getSubmitter()->getTeam())) {
                        return true;
                    }
                } else {
                    if ($section->containsUser($graded_gradeable->getSubmitter()->getUser())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a student is allowed to peer grade another
     * @param Gradeable $gradeable
     * @return bool
     */
    public function checkPeerAssignment(Gradeable $gradeable) {
        if (!$gradeable->getPeerGrading()) {
            return false;
        } else {
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            return in_array($gradeable->getUser()->getId(), $user_ids_to_grade);
        }
    }

    /**
     * Check if a student is allowed to peer grade another
     * @param GradedGradeable $graded_gradeable
     * @return bool
     */
    public function checkPeerAssignmentNewModel(GradedGradeable $graded_gradeable) {
        $gradeable = $graded_gradeable->getGradeable();
        if (!$gradeable->isPeerGrading()) {
            return false;
        } else {
            //FIXME: support teams
            $user_ids_to_grade = $this->core->getQueries()->getPeerAssignment($gradeable->getId(), $this->core->getUser()->getId());
            return in_array($graded_gradeable->getSubmitter()->getUser()->getId(), $user_ids_to_grade);
        }
    }

    /**
     * Check that a user group has privilege at least equal to minimum
     * @param int $check
     * @param int $minimum
     * @return bool
     */
    public function checkGroupPrivilege(int $check, int $minimum) {
        //Because access levels decrease as they get more powerful, this needs to be <=
        // If groups ever become non-sequential in the future, this needs to be replaced.
        return $check <= $minimum;
    }
}