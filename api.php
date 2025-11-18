<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUser = getCurrentUser();

try {
    switch ($action) {
        case 'create_project':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name)) {
                throw new Exception('Project name is required');
            }
            
            $stmt = $pdo->prepare("INSERT INTO projects (name, description, leader_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $currentUser['id']]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'create_board':
            $projectId = $_POST['project_id'] ?? '';
            $name = $_POST['name'] ?? '';
            
            if (empty($projectId) || empty($name)) {
                throw new Exception('Project ID and board name are required');
            }
            
            // Check if user is project leader
            $stmt = $pdo->prepare("SELECT leader_id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            
            if (!$project || $project['leader_id'] != $currentUser['id']) {
                throw new Exception('Only project leaders can create boards');
            }
            
            // Get max position
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM boards WHERE project_id = ?");
            $stmt->execute([$projectId]);
            $position = $stmt->fetch()['next_pos'];
            
            $stmt = $pdo->prepare("INSERT INTO boards (project_id, name, position) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $name, $position]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'create_card':
            $boardId = $_POST['board_id'] ?? '';
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assignedTo = $_POST['assigned_to'] ?? null;
            
            if (empty($boardId)) {
                throw new Exception('Board ID is required');
            }
            
            if (empty($title)) {
                throw new Exception('Card title is required');
            }
            
            // Verify user has access to this board's project
            $stmt = $pdo->prepare("
                SELECT p.id 
                FROM boards b
                INNER JOIN projects p ON b.project_id = p.id
                WHERE b.id = ? AND (p.leader_id = ? OR EXISTS (
                    SELECT 1 FROM project_members WHERE project_id = p.id AND user_id = ?
                ))
            ");
            $stmt->execute([$boardId, $currentUser['id'], $currentUser['id']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Access denied - You do not have permission to add cards to this board');
            }
            
            // Get max position
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 as next_pos FROM cards WHERE board_id = ?");
            $stmt->execute([$boardId]);
            $positionResult = $stmt->fetch();
            $position = $positionResult ? $positionResult['next_pos'] : 0;
            
            // Handle empty assigned_to
            if (empty($assignedTo) || $assignedTo === '') {
                $assignedTo = null;
            }
            
            // Insert card
            $stmt = $pdo->prepare("
                INSERT INTO cards (board_id, title, description, assigned_to, position, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $boardId, 
                $title, 
                $description, 
                $assignedTo, 
                $position,
                $currentUser['id']
            ]);
            
            if ($result) {
                $cardId = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'id' => $cardId,
                    'message' => 'Card created successfully'
                ]);
            } else {
                throw new Exception('Failed to create card');
            }
            break;
            
        case 'get_cards':
            $boardId = $_GET['board_id'] ?? '';
            
            if (empty($boardId)) {
                throw new Exception('Board ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       u1.full_name as assigned_name,
                       u2.full_name as created_by_name
                FROM cards c
                LEFT JOIN users u1 ON c.assigned_to = u1.id
                INNER JOIN users u2 ON c.created_by = u2.id
                WHERE c.board_id = ?
                ORDER BY c.position
            ");
            $stmt->execute([$boardId]);
            $cards = $stmt->fetchAll();
            
            echo json_encode($cards);
            break;
            
        case 'get_card':
            $cardId = $_GET['card_id'] ?? '';
            
            if (empty($cardId)) {
                throw new Exception('Card ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       u1.full_name as assigned_name,
                       u2.full_name as created_by_name
                FROM cards c
                LEFT JOIN users u1 ON c.assigned_to = u1.id
                INNER JOIN users u2 ON c.created_by = u2.id
                WHERE c.id = ?
            ");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch();
            
            if (!$card) {
                throw new Exception('Card not found');
            }
            
            echo json_encode($card);
            break;
            
        case 'update_card':
            $cardId = $_POST['card_id'] ?? '';
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $assignedTo = $_POST['assigned_to'] ?? null;
            
            if (empty($cardId) || empty($title)) {
                throw new Exception('Card ID and title are required');
            }
            
            // Verify user has access to update
            $stmt = $pdo->prepare("
                SELECT c.created_by, p.leader_id
                FROM cards c
                INNER JOIN boards b ON c.board_id = b.id
                INNER JOIN projects p ON b.project_id = p.id
                WHERE c.id = ?
            ");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch();
            
            if (!$card) {
                throw new Exception('Card not found');
            }
            
            $stmt = $pdo->prepare("
                UPDATE cards 
                SET title = ?, description = ?, assigned_to = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $assignedTo ?: null, $cardId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'move_card':
            $cardId = $_POST['card_id'] ?? '';
            $newBoardId = $_POST['board_id'] ?? '';
            $newPosition = $_POST['position'] ?? 0;
            
            if (empty($cardId) || empty($newBoardId)) {
                throw new Exception('Card ID and Board ID are required');
            }
            
            // Verify user has access
            $stmt = $pdo->prepare("
                SELECT c.board_id as old_board_id
                FROM cards c
                INNER JOIN boards b ON c.board_id = b.id
                INNER JOIN projects p ON b.project_id = p.id
                WHERE c.id = ? AND (p.leader_id = ? OR EXISTS (
                    SELECT 1 FROM project_members WHERE project_id = p.id AND user_id = ?
                ))
            ");
            $stmt->execute([$cardId, $currentUser['id'], $currentUser['id']]);
            $card = $stmt->fetch();
            
            if (!$card) {
                throw new Exception('Access denied');
            }
            
            // Update card's board and position
            $stmt = $pdo->prepare("
                UPDATE cards 
                SET board_id = ?, position = ?
                WHERE id = ?
            ");
            $stmt->execute([$newBoardId, $newPosition, $cardId]);
            
            // Reorder other cards in the new board
            $stmt = $pdo->prepare("
                UPDATE cards 
                SET position = position + 1 
                WHERE board_id = ? AND id != ? AND position >= ?
            ");
            $stmt->execute([$newBoardId, $cardId, $newPosition]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_board':
            $boardId = $_POST['board_id'] ?? '';
            
            if (empty($boardId)) {
                throw new Exception('Board ID is required');
            }
            
            // Check if user is project leader
            $stmt = $pdo->prepare("
                SELECT p.leader_id 
                FROM boards b
                INNER JOIN projects p ON b.project_id = p.id
                WHERE b.id = ?
            ");
            $stmt->execute([$boardId]);
            $board = $stmt->fetch();
            
            if (!$board || $board['leader_id'] != $currentUser['id']) {
                throw new Exception('Only project leaders can delete boards');
            }
            
            $stmt = $pdo->prepare("DELETE FROM boards WHERE id = ?");
            $stmt->execute([$boardId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete_card':
            $cardId = $_POST['card_id'] ?? '';
            
            if (empty($cardId)) {
                throw new Exception('Card ID is required');
            }
            
            // Verify user has access to delete (creator or project leader)
            $stmt = $pdo->prepare("
                SELECT c.created_by, p.leader_id
                FROM cards c
                INNER JOIN boards b ON c.board_id = b.id
                INNER JOIN projects p ON b.project_id = p.id
                WHERE c.id = ?
            ");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch();
            
            if (!$card || ($card['created_by'] != $currentUser['id'] && $card['leader_id'] != $currentUser['id'])) {
                throw new Exception('Access denied');
            }
            
            $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ?");
            $stmt->execute([$cardId]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'add_member':
            $projectId = $_POST['project_id'] ?? '';
            $userId = $_POST['user_id'] ?? '';
            
            if (empty($projectId) || empty($userId)) {
                throw new Exception('Project ID and User ID are required');
            }
            
            // Check if user is project leader
            $stmt = $pdo->prepare("SELECT leader_id FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            
            if (!$project || $project['leader_id'] != $currentUser['id']) {
                throw new Exception('Only project leaders can add members');
            }
            
            // Don't add if already a member
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO project_members (project_id, user_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$projectId, $userId]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>