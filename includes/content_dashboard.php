<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 20px;
        }

        .dashboard {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
        }

        .cards {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            flex: 1;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #555;
        }

        .card p {
            font-size: 28px;
            margin: 0;
            font-weight: bold;
            color: #333;
        }

        .charts {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }

        .chart-container {
            flex: 1 1 48%;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .chart-container canvas {
            width: 100% !important;
            height: 300px !important;
        }

        @media (max-width: 768px) {
            .cards {
                flex-direction: column;
            }
            .charts {
                flex-direction: column;
            }
            .chart-container {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard">
        <h1>Admin Dashboard</h1>

        <!-- Score Cards -->
        <div class="cards">
            <div class="card">
                <h2>Users</h2>
                <p>1,250</p>
            </div>
            <div class="card">
                <h2>Sales</h2>
                <p>₹78,500</p>
            </div>
            <div class="card">
                <h2>Visitors</h2>
                <p>9,870</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts">
            <div class="chart-container">
                <canvas id="barChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="lineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Bar Chart
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Visitors',
                    data: [1200, 1900, 3000, 2200, 1800],
                    backgroundColor: '#36A2EB'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Line Chart
        const lineCtx = document.getElementById('lineChart').getContext('2d');
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                datasets: [{
                    label: 'Sales (₹)',
                    data: [10000, 15000, 18000, 12000, 21000],
                    borderColor: '#FF6384',
                    backgroundColor: '#FF6384',
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    </script>
</body>
</html>
