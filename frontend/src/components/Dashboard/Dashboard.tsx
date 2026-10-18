import React, { useState, useEffect } from 'react';
import { useApi } from '../../hooks/useApi';
import { AlertCard } from './AlertCard';
import { StatsGrid } from './StatsGrid';
import './Dashboard.css';

export const Dashboard: React.FC = () => {
  const { get } = useApi();
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      setLoading(true);
      const response = await get('/dashboard');
      setDashboard(response.data);
      setError(null);
    } catch (err) {
      setError('Failed to load dashboard');
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div className="dashboard-loading">Chargement...</div>;
  if (error) return <div className="dashboard-error">{error}</div>;
  if (!dashboard) return null;

  return (
    <div className="dashboard">
      <h1>Tableau de Bord</h1>
      
      <StatsGrid stats={dashboard.stats} />

      <section className="dashboard-alerts">
        <h2>Alertes Critiques ({dashboard.critical_alerts.length})</h2>
        <div className="alerts-grid">
          {dashboard.critical_alerts.map((alert: any) => (
            <AlertCard key={alert.id} alert={alert} />
          ))}
        </div>
      </section>

      <section className="dashboard-troubled">
        <h2>Étudiants en Difficulté</h2>
        <div className="students-list">
          {dashboard.troubled_students.map((student: any) => (
            <div key={student.id} className="student-row">
              <span className="student-name">{student.nom} {student.prenom}</span>
              <span className="student-avg">{student.moyenne?.toFixed(2) || 'N/A'}/20</span>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
};
