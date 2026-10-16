<template>
    <div class="attendance-card">
        <div class="card-header">
            <h3 class="card-title">Attendance Summary</h3>
            <div class="month-indicator">
                <span class="current-month">JULY 2025</span>
            </div>
        </div>

        <div class="card-body">
            <div class="attendance-stats">
                <div class="stat-grid">
                    <div class="stat-item present">
                        <div class="stat-value">18</div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-item late">
                        <div class="stat-value">2</div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="stat-item early-leave">
                        <div class="stat-value">1</div>
                        <div class="stat-label">Early Leave</div>
                    </div>
                    <div class="stat-item absent">
                        <div class="stat-value">0</div>
                        <div class="stat-label">Absent</div>
                    </div>
                </div>

                <div class="attendance-rate">
                    <div class="rate-circle">
                        <div class="rate-value">86%</div>
                        <div class="rate-label">Attendance Rate</div>
                    </div>
                    <div class="working-days">
                        <span class="days-value">21</span> working days
                    </div>
                </div>
            </div>

            <div class="shift-info">
                <div class="current-shift">
                    <div class="info-label">TODAY'S SHIFT</div>
                    <div class="info-value">NON SHIFT</div>
                    <div class="time-range">08:00 - 16:30</div>
                </div>
                <div class="next-shift">
                    <div class="info-label">TOMORROW'S SHIFT</div>
                    <div class="info-value">OGK2</div>
                </div>
            </div>

            <div class="additional-stats">
                <div class="additional-stat">
                    <div class="stat-icon">
                        <i class="bi bi-alarm"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-title">Total Late</div>
                        <div class="stat-number">1</div>
                    </div>
                </div>
                <div class="additional-stat">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-title">Total Overtime</div>
                        <div class="stat-number">1</div>
                    </div>
                </div>
                <div class="additional-stat">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-title">Remaining Leave</div>
                        <div class="stat-number">1</div>
                    </div>
                </div>
            </div>

            <div class="calendar-week">
                <div
                    class="day"
                    :class="{ 'current-day': day === 26 }"
                    v-for="day in [21, 22, 23, 24, 25, 26, 27]"
                    :key="day"
                >
                    <div class="day-name">{{ getDayName(day) }}</div>
                    <div class="day-number">{{ day }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "AttendanceCard",
    methods: {
        getDayName(day) {
            const days = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];
            const startIndex = 0; // Starting from Sunday (21st is Sunday based on your data)
            const index = (day - 21) % 7;
            return days[index];
        },
    },
};
</script>

<style scoped>
.attendance-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    font-family: "Segoe UI", sans-serif;
    max-width: 400px;
    margin: 0 auto;
}

.card-header {
    background: #4a6cf7;
    color: white;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.current-month {
    font-size: 14px;
    opacity: 0.9;
}

.card-body {
    padding: 20px;
}

.attendance-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    width: 55%;
}

.stat-item {
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}

.stat-item.present {
    background: rgba(74, 108, 247, 0.1);
    color: #4a6cf7;
}

.stat-item.late {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.stat-item.early-leave {
    background: rgba(255, 152, 0, 0.1);
    color: #ff9800;
}

.stat-item.absent {
    background: rgba(244, 67, 54, 0.1);
    color: #f44336;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    margin-top: 4px;
}

.attendance-rate {
    width: 40%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.rate-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #4a6cf7;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
}

.rate-value {
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.rate-label {
    font-size: 10px;
    opacity: 0.9;
}

.working-days {
    font-size: 12px;
    color: #666;
    text-align: center;
}

.days-value {
    font-weight: 600;
    color: #333;
}

.shift-info {
    display: flex;
    justify-content: space-between;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
}

.current-shift,
.next-shift {
    flex: 1;
}

.info-label {
    font-size: 10px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.time-range {
    font-size: 12px;
    color: #4a6cf7;
}

.additional-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.additional-stat {
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    flex: 1;
    margin: 0 5px;
}

.stat-icon {
    background: #4a6cf7;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.stat-icon i {
    font-size: 14px;
}

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-title {
    font-size: 10px;
    color: #666;
}

.stat-number {
    font-size: 14px;
    font-weight: 600;
}

.calendar-week {
    display: flex;
    justify-content: space-between;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px 5px;
}

.day {
    text-align: center;
    padding: 5px;
    border-radius: 6px;
    flex: 1;
}

.day.current-day {
    background: #4a6cf7;
    color: white;
}

.day-name {
    font-size: 10px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.day-number {
    font-size: 14px;
    font-weight: 600;
}
</style>
