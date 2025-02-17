<?php

namespace App\Models;

use App\Models\BaseModel;

class Attendance extends BaseModel
{
    // Fetch the latest 5 attendance records
    public function getLatestAttendance($limit = 5)
    {
        $stmt = $this->db->prepare("
            SELECT 
                a.EmployeeID, 
                e.Name as EmployeeName, 
                d.DepartmentName, 
                a.InTime, 
                a.InStatus, 
                a.OutTime, 
                a.OutStatus 
            FROM Attendance a
            JOIN Employee e ON a.EmployeeID = e.ID
            JOIN Department d ON a.DepartmentID = d.ID
            ORDER BY a.InTime DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAllAttendance()
{
    $stmt = $this->db->prepare("
        SELECT 
            a.EmployeeID, 
            e.Name as EmployeeName, 
            d.DepartmentName, 
            a.InTime, 
            a.InStatus, 
            a.OutTime, 
            a.OutStatus,
            a.ShiftID
        FROM Attendance a
        JOIN Employee e ON a.EmployeeID = e.ID
        JOIN Department d ON a.DepartmentID = d.ID
        ORDER BY a.InTime DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Return all attendance records
}

public function getFilteredAttendance($filters)
{
    $query = "
        SELECT a.EmployeeID, e.Name as EmployeeName, d.DepartmentName, 
               a.InTime, a.InStatus, a.OutTime, a.OutStatus, a.ShiftID 
        FROM Attendance a
        JOIN Employee e ON a.EmployeeID = e.ID
        JOIN Department d ON a.DepartmentID = d.ID
        WHERE 1=1
    ";

    // Add filtering conditions dynamically
    if (!empty($filters['EmployeeID'])) {
        $query .= " AND a.EmployeeID = :EmployeeID";
    }
    if (!empty($filters['EmployeeName'])) {
        $query .= " AND e.Name LIKE :EmployeeName";
    }
    if (!empty($filters['DepartmentName'])) {
        $query .= " AND d.DepartmentName LIKE :DepartmentName";
    }
    if (!empty($filters['ShiftID'])) {
        $query .= " AND a.ShiftID = :ShiftID";
    }
    if (!empty($filters['InStatus'])) {
        $query .= " AND a.InStatus = :InStatus";
    }
    if (!empty($filters['OutStatus'])) {
        $query .= " AND a.OutStatus = :OutStatus";
    }

    $stmt = $this->db->prepare($query);

    if (!empty($filters['EmployeeID'])) {
        $stmt->bindParam(':EmployeeID', $filters['EmployeeID']);
    }
    if (!empty($filters['EmployeeName'])) {
        $stmt->bindValue(':EmployeeName', "%" . $filters['EmployeeName'] . "%");
    }
    if (!empty($filters['DepartmentName'])) {
        $stmt->bindValue(':DepartmentName', "%" . $filters['DepartmentName'] . "%");
    }
    if (!empty($filters['ShiftID'])) {
        $stmt->bindParam(':ShiftID', $filters['ShiftID']);
    }
    if (!empty($filters['InStatus'])) {
        $stmt->bindParam(':InStatus', $filters['InStatus']);
    }
    if (!empty($filters['OutStatus'])) {
        $stmt->bindParam(':OutStatus', $filters['OutStatus']);
    }

    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}



    // Count present employees
    public function countPresent()
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM Attendance 
            WHERE InStatus = 'Present' AND DATE(InTime) = CURDATE()
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Count late employees
    public function countLate()
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM Attendance 
            WHERE InStatus = 'Late' AND DATE(InTime) = CURDATE()
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getAllAttendanceByEmployee($employeeId)
    {
        // Fetch all attendance records for the given employee, ordered by InTime (descending)
        $stmt = $this->db->prepare("
            SELECT shiftID, InTime, InStatus, OutTime, OutStatus 
            FROM Attendance 
            WHERE EmployeeID = :employeeId
            ORDER BY InTime DESC
        ");
        $stmt->bindParam(':employeeId', $employeeId);
        $stmt->execute();
    
        // Return the results as an associative array
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    

public function recordTimeIn($employeeId)
{
    // Fetch the employee's department ID and the shift they are assigned to
    $stmt = $this->db->prepare("
        SELECT e.DepartmentID, ds.ShiftID, s.TimeIn 
        FROM Employee e
        JOIN DepartmentShifts ds ON e.DepartmentID = ds.DepartmentID
        JOIN Shift s ON ds.ShiftID = s.ID
        WHERE e.ID = :employeeId
    ");
    $stmt->bindParam(':employeeId', $employeeId);
    $stmt->execute();
    $shiftData = $stmt->fetch();

    if ($shiftData) {
        $departmentID = $shiftData['DepartmentID'];
        $shiftID = $shiftData['ShiftID'];
        $scheduledTimeIn = $shiftData['TimeIn']; // The scheduled start time of the shift

        // Check if the employee clocked in late
        $currentTime = date("H:i:s"); // Get current time

        // Compare with scheduled TimeIn
        if ($currentTime > $scheduledTimeIn) {
            $inStatus = 'Late';
        } else {
            $inStatus = 'On Time';
        }

        // Record the clock-in time
        $stmt = $this->db->prepare("
            INSERT INTO Attendance (EmployeeID, DepartmentID, ShiftID, InTime, InStatus)
            VALUES (:employeeId, :departmentID, :shiftID, NOW(), :inStatus)
        ");
        $stmt->bindParam(':employeeId', $employeeId);
        $stmt->bindParam(':departmentID', $departmentID);
        $stmt->bindParam(':shiftID', $shiftID);
        $stmt->bindParam(':inStatus', $inStatus);
        $stmt->execute();
    }
}

public function recordTimeOut($employeeId)
{
    // Fetch the employee's department ID and the shift they are assigned to
    $stmt = $this->db->prepare("
        SELECT e.DepartmentID, ds.ShiftID, s.TimeOut 
        FROM Employee e
        JOIN DepartmentShifts ds ON e.DepartmentID = ds.DepartmentID
        JOIN Shift s ON ds.ShiftID = s.ID
        WHERE e.ID = :employeeId
    ");
    $stmt->bindParam(':employeeId', $employeeId);
    $stmt->execute();
    $shiftData = $stmt->fetch();

    if ($shiftData) {
        $departmentID = $shiftData['DepartmentID'];
        $shiftID = $shiftData['ShiftID'];
        $scheduledTimeOut = $shiftData['TimeOut']; // The scheduled end time of the shift

        // Check if the employee clocked out late
        $currentTime = date("H:i:s"); // Get current time

        // Compare with scheduled TimeOut
        if ($currentTime > $scheduledTimeOut) {
            $outStatus = 'Late';
        } else {
            $outStatus = 'Completed';
        }

        // Update the clock-out time and status
        $stmt = $this->db->prepare("
            UPDATE Attendance 
            SET OutTime = NOW(), OutStatus = :outStatus 
            WHERE EmployeeID = :employeeId AND DATE(InTime) = CURDATE()
        ");
        $stmt->bindParam(':employeeId', $employeeId);
        $stmt->bindParam(':outStatus', $outStatus);
        $stmt->execute();
    }
}

}