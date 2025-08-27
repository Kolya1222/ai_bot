<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Bot Manager - @yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .chat-message {
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 10px;
            max-width: 80%;
        }
        .user-message {
            background-color: #007bff;
            color: white;
            margin-left: auto;
        }
        .bot-message {
            background-color: #e9ecef;
            color: #333;
        }
        .session-item:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar py-3">
                <div class="position-sticky">
                    <h5 class="px-3 mb-3">AI Bot Manager</h5>
                    <div class="mt-4 px-3">
                        <h6>Быстрая статистика</h6>
                        <div id="quick-stats">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 col-lg-10 px-md-4 py-4">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Базовые функции для работы с API
        const apiManager = {
            async get(url) {
                const response = await fetch(url);
                return await response.json();
            },
            
            async delete(url) {
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('_method', 'DELETE'); // Важно!

                const response = await fetch(url, {
                    method: 'POST', // Используем POST метод
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                return await response.json();
            }
        };
    </script>
    @yield('scripts')
</body>
</html>