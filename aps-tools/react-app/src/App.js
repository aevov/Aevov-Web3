import React from 'react';
import { HashRouter as Router, Route, Switch } from 'react-router-dom';
import Dashboard from './components/Dashboard';
import Visualizations from './components/Visualizations';

function App() {
    return (
        <Router>
            <Switch>
                <Route path="/dashboard" component={Dashboard} />
                <Route path="/visualizations" component={Visualizations} />
            </Switch>
        </Router>
    );
}

export default App;
