import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'

function App() {
  return (
    <BrowserRouter>
      <div className="min-h-screen bg-background text-foreground">
        <div className="flex items-center justify-center h-screen">
          <div className="text-center">
            <h1 className="text-4xl font-bold mb-4">Agentis Studio</h1>
            <p className="text-muted-foreground">
              Universal AI skill configuration manager
            </p>
          </div>
        </div>
      </div>
      <Routes>
        <Route path="/" element={<Navigate to="/projects" replace />} />
        <Route path="/projects" element={<div>Projects</div>} />
        <Route path="/projects/:id" element={<div>Project Detail</div>} />
        <Route path="/skills/new" element={<div>New Skill</div>} />
        <Route path="/skills/:id" element={<div>Edit Skill</div>} />
        <Route path="/library" element={<div>Library</div>} />
        <Route path="/search" element={<div>Search</div>} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
