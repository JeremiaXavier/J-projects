<?php
require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();
$selectedProjectId = $_GET['project'] ?? null;

// Get all projects where user is leader or member
$stmt = $pdo->prepare("
    SELECT DISTINCT p.*, u.full_name as leader_name,
           (p.leader_id = ?) as is_leader
    FROM projects p
    INNER JOIN users u ON p.leader_id = u.id
    LEFT JOIN project_members pm ON p.id = pm.project_id
    WHERE p.leader_id = ? OR pm.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id']]);
$projects = $stmt->fetchAll();

// Get selected project details
$selectedProject = null;
$boards = [];
$projectMembers = [];

if ($selectedProjectId) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as leader_name,
               (p.leader_id = ?) as is_leader
        FROM projects p
        INNER JOIN users u ON p.leader_id = u.id
        WHERE p.id = ? AND (p.leader_id = ? OR EXISTS (
            SELECT 1 FROM project_members WHERE project_id = p.id AND user_id = ?
        ))
    ");
    $stmt->execute([$currentUser['id'], $selectedProjectId, $currentUser['id'], $currentUser['id']]);
    $selectedProject = $stmt->fetch();

    if ($selectedProject) {
        // Get boards for this project
        $stmt = $pdo->prepare("SELECT * FROM boards WHERE project_id = ? ORDER BY position");
        $stmt->execute([$selectedProjectId]);
        $boards = $stmt->fetchAll();

        // Get project members
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.username,
                   (p.leader_id = u.id) as is_leader
            FROM users u
            LEFT JOIN projects p ON p.id = ? AND p.leader_id = u.id
            WHERE u.id = ? OR EXISTS (
                SELECT 1 FROM project_members WHERE project_id = ? AND user_id = u.id
            )
            ORDER BY is_leader DESC, u.full_name
        ");
        $stmt->execute([$selectedProjectId, $selectedProject['leader_id'], $selectedProjectId]);
        $projectMembers = $stmt->fetchAll();
    }
}

// Get all users for adding members
$stmt = $pdo->query("SELECT id, username, full_name FROM users ORDER BY full_name");
$allUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JProjects</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
    <link rel="manifest" href="assets/site.webmanifest">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .board-column {
            min-width: 320px;
            max-width: 320px;
        }

        .card-item {
            cursor: move;
            transition: all 0.2s ease;
        }

        .card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sortable-ghost {
            opacity: 0.4;
            background: #e3f2fd;
        }

        .sortable-drag {
            opacity: 0.8;
            transform: rotate(2deg);
        }

        .cards-container {
            min-height: 100px;
        }
    </style>
</head>

<body class="bg-blue-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-md">
        <div class="max-w-full mx-auto px-6 py-3 flex items-center justify-between">

            <div class="flex items-center space-x-8">

                <a href="/"
                    class="flex items-center space-x-2 text-xl font-extrabold text-gray-900 hover:text-gray-800 transition">
                    <div class="bg-gray-900 p-1 ">
                        <img src="j.png" class="w-6 h-6 invert" alt="JProjects Logo">
                    </div>
                    <span>JProjects</span>
                </a>

                <nav class="hidden md:flex space-x-6 text-base font-medium text-gray-600">
                    <a href="#" class="hover:text-blue-600 transition">Dashboard</a>
                    <a href="https://jxchat.xo.je" target="_blank" class="hover:text-blue-600 transition">Chat</a>
                    <a href="#" class="hover:text-blue-600 transition">Reports</a>
                </nav>
            </div>

            <div class="flex items-center space-x-5">

                <span class="text-sm font-medium text-gray-600">
                    Welcome, <strong
                        class="text-blue-700"><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>
                </span>

                <a href="register.php"
                    class="flex items-center text-blue-700 hover:text-blue-900 text-sm font-semibold p-2 rounded-md transition duration-150">
                    <i class="fas fa-user-plus mr-1.5"></i>
                </a>

                <a href="logout.php"
                    class="text-red-600 hover:text-red-700 text-sm font-medium px-4 py-2 border border-transparent hover:border-red-300 rounded-md transition duration-150">
                    Logout
                </a>

            </div>
        </div>
    </header>

    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-lg overflow-y-auto">
            <div class="p-4">
                <a href="#" onclick="showCreateProjectModal()"
                    class="flex items-center w-full justify-center text-blue-700 hover:bg-blue-50 hover:text-blue-800 font-semibold py-2 px-4 rounded-md transition duration-150 mb-4">

                    <i class="fas fa-plus-circle mr-2 text-lg"></i>

                    <span class="text-base">New Project</span>
                </a>

                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Your Projects</h3>
                <div class="space-y-1 mt-4"> <?php foreach ($projects as $project): ?>
                        <a href="?project=<?php echo $project['id']; ?>" class="
                flex justify-between items-center 
                px-3 py-2 
                rounded-lg transition duration-150 bg-gray-200
                <?php echo $selectedProjectId == $project['id']
                    // ACTIVE STATE: Use a primary color background and bold text
                    ? 'bg-blue-600 text-dark shadow-md'
                    // INACTIVE STATE: Subtle text, clear hover background
                    : 'text-gray-700 hover:bg-gray-100';
                ?>
            ">
                            <div
                                class="<?php echo $selectedProjectId == $project['id'] ? 'font-semibold' : 'font-medium'; ?>">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </div>

                            <div class="
                text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full
                <?php echo $selectedProjectId == $project['id']
                    ? 'bg-blue-600 bg-opacity-20 text-white' // White badge on blue active state
                    : ($project['is_leader'] ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-600');
                ?>
            ">
                                <?php echo $project['is_leader'] ? 'Leader' : 'Member'; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>

                    <?php if (empty($projects)): ?>
                        <p class="text-gray-500 text-sm px-3 py-2">No projects yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-hidden flex flex-col">
            <?php if ($selectedProject): ?>
                <!-- Project Header -->
                <div class="bg-white border-b border-gray-200 p-4 flex-shrink-0">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($selectedProject['name']); ?>
                            </h2>
                            <p class="text-sm text-gray-600">Led by
                                <?php echo htmlspecialchars($selectedProject['leader_name']); ?>
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($selectedProject['is_leader']): ?>
                                <div class="flex items-center space-x-3">

                                    <a href="#" onclick="showAddMemberModal()"
                                        class="flex items-center text-gray-700 hover:text-blue-700 hover:bg-gray-100 px-3 py-1.5 rounded-md font-medium transition duration-150">
                                        <i class="fas fa-user-plus mr-1.5"></i>
                                        <span>Add Member</span>
                                    </a>

                                    <a href="#" onclick="showAddBoardModal()"
                                        class="flex items-center text-blue-700 hover:text-blue-900 hover:bg-blue-50 px-3 py-1.5 rounded-md font-medium transition duration-150 border border-transparent">
                                        <i class="fas fa-chalkboard-teacher mr-1.5"></i>
                                        <span>Add Board</span>
                                    </a>

                                </div>
                            <?php endif; ?>
                            <a href="#" onclick="showMembersModal()"
                                class="flex items-center bg-gray-100 text-gray-700 hover:bg-blue-50 hover:text-blue-700 font-medium px-3 py-1.5 rounded-full transition duration-150 text-sm">

                                <i class="fas fa-users mr-2"></i>

                                <span>Team
                                    <span class="font-semibold">
                                        (<?php echo count($projectMembers); ?>)
                                    </span>
                                </span>
                            </a>
                        </div>
                    </div>
                    <?php if ($selectedProject['description']): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($selectedProject['description']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Boards Container -->
                <div class="flex-1 overflow-x-auto overflow-y-hidden p-4">
                    <div class="flex space-x-4 h-full" id="boardsContainer">
                        <?php foreach ($boards as $board): ?>
                            <div class="board-column bg-blue-100 rounded-lg p-3 flex flex-col"
                                data-board-id="<?php echo $board['id']; ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-800 text-lg">
                                        <?php echo htmlspecialchars($board['name']); ?>
                                    </h3>
                                    <button onclick="showAddCardModal(<?php echo $board['id']; ?>)"
                                        class="bg-transparent hover:bg-gray-50 text-gray-700 py-1 px-2 rounded border border-gray-300 font-medium">
                                        + Add Card
                                    </button>
                                    <?php if ($selectedProject['is_leader']): ?>
                                        <button onclick="deleteBoard(<?php echo $board['id']; ?>)"
                                            class="text-red-500 hover:text-red-700">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    <?php endif; ?>

                                </div>

                                <div class="flex-1 overflow-y-auto space-y-2 mb-3 cards-container"
                                    id="board-<?php echo $board['id']; ?>" data-board-id="<?php echo $board['id']; ?>">
                                    <!-- Cards will be loaded here via AJAX -->
                                </div>


                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($boards)): ?>
                            <div class="text-center text-gray-500 py-20 w-full">
                                <svg class="w-20 h-20 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2">
                                    </path>
                                </svg>
                                <p class="text-xl font-semibold">No boards yet</p>
                                <?php if ($selectedProject['is_leader']): ?>
                                    <p class="mt-2">Click "Add Board" to create your first board</p>
                                    <p class="text-sm text-gray-400 mt-1">(e.g., To Do, In Progress, Done)</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Project Selected -->
                <div class="flex items-center justify-center h-full">
                    <div class="text-center text-gray-500">
                        <svg class="w-24 h-24 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                        <p class="text-xl font-semibold">Select a project to get started</p>
                        <p class="mt-2">or create a new one</p>
                    </div>
                </div>
            <?php endif; ?>
            <footer class=" bg-transparent text-gray-500 text-sm py-2 px-6 border-t border-gray-200">
                <div class="max-w-7xl mx-auto flex justify-between items-center">

                    <p class="font-medium">
                        &copy; <?php echo date("Y"); ?> Jeremia Xavier
                    </p>

                    <div class="flex space-x-4">
                        <a href="#" class="hover:text-blue-600 transition">Privacy Policy</a>
                        <a href="#" class="hover:text-blue-600 transition">Terms of Service</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Modals -->
    <!-- Create Project Modal -->
    <div id="createProjectModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Create New Project</h3>
            <form id="createProjectForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                    <input type="text" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideModal('createProjectModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Board Modal -->
    <div id="addBoardModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Add New Board</h3>
            <form id="addBoardForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Board Name</label>
                    <input type="text" name="name" required placeholder="e.g., To Do, In Progress, Done"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideModal('addBoardModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add
                        Board</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Card Modal -->
    <div id="addCardModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Add New Card</h3>
            <form id="addCardForm">
                <input type="hidden" name="board_id" id="cardBoardId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                    <input type="text" name="title" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter task title">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Add more details..."></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                    <select name="assigned_to"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($projectMembers as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name']); ?>
                                <?php echo $member['is_leader'] ? ' (Leader)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideModal('addCardModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add
                        Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View/Edit Card Modal -->
    <div id="viewCardModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-xl font-bold">Card Details</h3>
                <button onclick="hideModal('viewCardModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form id="editCardForm">
                <input type="hidden" name="card_id" id="editCardId">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                    <input type="text" name="title" id="editCardTitle" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="editCardDescription" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                    <select name="assigned_to" id="editCardAssignedTo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($projectMembers as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name']); ?>
                                <?php echo $member['is_leader'] ? ' (Leader)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4 p-3 bg-gray-50 rounded text-sm">
                    <div class="flex justify-between mb-1">
                        <span class="text-gray-600">Created by:</span>
                        <span class="font-medium" id="cardCreatedBy"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Created at:</span>
                        <span class="font-medium" id="cardCreatedAt"></span>
                    </div>
                </div>

                <div class="flex justify-between space-x-2">
                    <button type="button" onclick="deleteCardFromModal()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete Card
                    </button>
                    <div class="flex space-x-2">
                        <button type="button" onclick="hideModal('viewCardModal')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save
                            Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div id="addMemberModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Add Team Member</h3>
            <form id="addMemberForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select User</label>
                    <select name="user_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Choose a user...</option>
                        <?php foreach ($allUsers as $user): ?>
                            <?php
                            $isMember = false;
                            foreach ($projectMembers as $member) {
                                if ($member['id'] == $user['id']) {
                                    $isMember = true;
                                    break;
                                }
                            }
                            if (!$isMember):
                                ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                    (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideModal('addMemberModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Add
                        Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Modal -->
    <div id="membersModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Team Members</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php foreach ($projectMembers as $member): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div>
                            <div class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></div>
                            <div class="text-sm text-gray-600">@<?php echo htmlspecialchars($member['username']); ?></div>
                        </div>
                        <span
                            class="px-2 py-1 text-xs font-semibold rounded <?php echo $member['is_leader'] ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-700'; ?>">
                            <?php echo $member['is_leader'] ? 'Leader' : 'Member'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="hideModal('membersModal')"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Close</button>
            </div>
        </div>
    </div>

    <script>
        const projectId = <?php echo $selectedProjectId ?: 'null'; ?>;
        let currentCardId = null;

        // Initialize Sortable for drag and drop
        function initSortable() {
            $('.cards-container').each(function () {
                const boardId = $(this).data('board-id');

                new Sortable(this, {
                    group: 'cards',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: function (evt) {
                        const cardId = $(evt.item).data('card-id');
                        const newBoardId = $(evt.to).data('board-id');
                        const newPosition = evt.newIndex;

                        // Update card position via AJAX
                        $.post('api.php', {
                            action: 'move_card',
                            card_id: cardId,
                            board_id: newBoardId,
                            position: newPosition
                        }).fail(function () {
                            alert('Error moving card');
                            loadAllCards();
                        });
                    }
                });
            });
        }

        // Load cards on page load
        $(document).ready(function () {
            if (projectId) {
                loadAllCards();
            }
        });

        // Modal functions
        function showCreateProjectModal() {
            $('#createProjectModal').removeClass('hidden');
        }

        function showAddBoardModal() {
            $('#addBoardModal').removeClass('hidden');
        }

        function showAddCardModal(boardId) {
            $('#cardBoardId').val(boardId);
            $('#addCardModal').removeClass('hidden');
        }

        function showAddMemberModal() {
            $('#addMemberModal').removeClass('hidden');
        }

        function showMembersModal() {
            $('#membersModal').removeClass('hidden');
        }

        function hideModal(modalId) {
            $('#' + modalId).addClass('hidden');
        }

        function showViewCardModal(cardId) {
            currentCardId = cardId;
            // Get card details
            $.ajax({
                url: 'api.php',
                type: 'GET',
                data: {
                    action: 'get_card',
                    card_id: cardId
                },
                dataType: 'json',
                success: function (response) {
                    const card = typeof response === 'string' ? JSON.parse(response) : response;
                    $('#editCardId').val(card.id);
                    $('#editCardTitle').val(card.title);
                    $('#editCardDescription').val(card.description || '');
                    $('#editCardAssignedTo').val(card.assigned_to || '');
                    $('#cardCreatedBy').text(card.created_by_name);
                    $('#cardCreatedAt').text(new Date(card.created_at).toLocaleString());
                    $('#viewCardModal').removeClass('hidden');
                },
                error: function (xhr) {
                    console.error('Error loading card:', xhr.responseText);
                    alert('Error loading card details');
                }
            });
        }

        function deleteCardFromModal() {
            if (confirm('Are you sure you want to delete this card?')) {
                deleteCard(currentCardId);
                hideModal('viewCardModal');
            }
        }

        // Create project
        $('#createProjectForm').on('submit', function (e) {
            e.preventDefault();
            $.post('api.php', {
                action: 'create_project',
                name: $('[name="name"]', this).val(),
                description: $('[name="description"]', this).val()
            }).done(function () {
                location.reload();
            }).fail(function () {
                alert('Error creating project');
            });
        });

        // Add board
        $('#addBoardForm').on('submit', function (e) {
            e.preventDefault();
            $.post('api.php', {
                action: 'create_board',
                project_id: projectId,
                name: $('[name="name"]', this).val()
            }).done(function () {
                location.reload();
            }).fail(function () {
                alert('Error creating board');
            });
        });

        // Add card
        $('#addCardForm').on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serializeArray();
            const data = { action: 'create_card' };
            formData.forEach(item => data[item.name] = item.value);

            console.log('Submitting card data:', data); // Debug log

            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    console.log('Card created successfully:', response);
                    hideModal('addCardModal');
                    $('#addCardForm')[0].reset();
                    // Reload cards after a short delay to ensure DB is updated
                    setTimeout(function () {
                        loadAllCards();
                    }, 100);
                },
                error: function (xhr, status, error) {
                    console.error('Error creating card:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });

                    let errorMsg = 'Error creating card. ';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMsg += errorResponse.error || 'Please try again.';
                    } catch (e) {
                        errorMsg += 'Server error. Response: ' + xhr.responseText.substring(0, 100);
                    }
                    alert(errorMsg);
                }
            });
        });

        // Edit card
        $('#editCardForm').on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serializeArray();
            const data = { action: 'update_card' };
            formData.forEach(item => data[item.name] = item.value);

            $.post('api.php', data).done(function () {
                hideModal('viewCardModal');
                loadAllCards();
            }).fail(function () {
                alert('Error updating card');
            });
        });

        // Add member
        $('#addMemberForm').on('submit', function (e) {
            e.preventDefault();
            $.post('api.php', {
                action: 'add_member',
                project_id: projectId,
                user_id: $('[name="user_id"]', this).val()
            }).done(function () {
                location.reload();
            }).fail(function () {
                alert('Error adding member');
            });
        });

        // Delete board
        function deleteBoard(boardId) {
            if (confirm('Are you sure you want to delete this board and all its cards?')) {
                $.post('api.php', {
                    action: 'delete_board',
                    board_id: boardId
                }).done(function () {
                    location.reload();
                }).fail(function () {
                    alert('Error deleting board');
                });
            }
        }

        // Delete card
        function deleteCard(cardId) {
            if (confirm('Are you sure you want to delete this card?')) {
                $.post('api.php', {
                    action: 'delete_card',
                    card_id: cardId
                }).done(function () {
                    loadAllCards();
                }).fail(function () {
                    alert('Error deleting card');
                });
            }
        }

        // Load all cards
        function loadAllCards() {
            $('.board-column').each(function () {
                const boardId = $(this).data('board-id');
                loadCards(boardId);
            });
        }

        // Load cards for a board
        function loadCards(boardId) {
            $.get('api.php', {
                action: 'get_cards',
                board_id: boardId
            }).done(function (response) {
                console.log('Raw response for board ' + boardId + ':', response); // Debug

                let cards;
                try {
                    // Check if response is already an object
                    if (typeof response === 'string') {
                        cards = JSON.parse(response);
                    } else {
                        cards = response;
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e, 'Response:', response);
                    alert('Error loading cards. Check console for details.');
                    return;
                }

                const container = $('#board-' + boardId);
                container.empty();

                if (!Array.isArray(cards)) {
                    console.error('Cards is not an array:', cards);
                    return;
                }

                cards.forEach(card => {
                    const assignedTo = card.assigned_name ?
                        `<div class="flex items-center text-xs text-blue-600 mt-2">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                            </svg>
                            ${escapeHtml(card.assigned_name)}
                        </div>` :
                        `<div class="text-xs text-gray-400 mt-2">Unassigned</div>`;

                    const cardHtml = `
                        <div class="card-item bg-white p-3 rounded-lg shadow-sm border border-gray-200 transition" 
                             data-card-id="${card.id}"
                             onclick="showViewCardModal(${card.id})">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-medium text-gray-800 flex-1 pr-2">${escapeHtml(card.title)}</h4>
                            </div>
                            ${card.description ? `<p class="text-sm text-gray-600 mb-2 line-clamp-2">${escapeHtml(card.description)}</p>` : ''}
                            <div class="flex items-center justify-between">
                                ${assignedTo}
                                <span class="text-xs text-gray-400">${escapeHtml(card.created_by_name)}</span>
                            </div>
                        </div>
                    `;
                    container.append(cardHtml);
                });

                // Reinitialize sortable after loading cards
                initSortable();
            }).fail(function (xhr) {
                console.error('Error loading cards for board ' + boardId, xhr.responseText);
            });
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>