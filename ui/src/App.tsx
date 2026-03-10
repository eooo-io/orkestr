import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { Layout } from '@/components/layout/Layout'
import { Projects } from '@/pages/Projects'
import { ProjectDetail } from '@/pages/ProjectDetail'
import { SkillEditor } from '@/pages/SkillEditor'
import { Library } from '@/pages/Library'
import { Search } from '@/pages/Search'
import { Settings } from '@/pages/Settings'
import { Playground } from '@/pages/Playground'
import { ProjectForm } from '@/pages/ProjectForm'

function App() {
  return (
    <BrowserRouter>
      <Layout>
        <Routes>
          <Route path="/" element={<Navigate to="/projects" replace />} />
          <Route path="/projects" element={<Projects />} />
          <Route path="/projects/new" element={<ProjectForm />} />
          <Route path="/projects/:id" element={<ProjectDetail />} />
          <Route path="/projects/:id/edit" element={<ProjectForm />} />
          <Route path="/skills/new" element={<SkillEditor />} />
          <Route path="/skills/:id" element={<SkillEditor />} />
          <Route path="/library" element={<Library />} />
          <Route path="/playground" element={<Playground />} />
          <Route path="/search" element={<Search />} />
          <Route path="/settings" element={<Settings />} />
        </Routes>
      </Layout>
    </BrowserRouter>
  )
}

export default App
